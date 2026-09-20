package main

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"os"
	"os/signal"
	"syscall"

	"github.com/aws/aws-sdk-go-v2/aws"
	awsconfig "github.com/aws/aws-sdk-go-v2/config"
	"github.com/aws/aws-sdk-go-v2/service/s3"
	"github.com/aws/aws-sdk-go-v2/service/sqs"
	"github.com/aws/aws-sdk-go-v2/service/sqs/types"
	_ "modernc.org/sqlite"
)

type jobMessage struct {
	DocumentID int64  `json:"document_id"`
	S3Key      string `json:"s3_key"`
}

func main() {
	log.SetFlags(log.LstdFlags | log.Lmsgprefix)
	log.SetPrefix("worker ")

	cfg, err := loadConfig()
	if err != nil {
		log.Fatal(err)
	}

	store, err := openStore(cfg.SQLitePath)
	if err != nil {
		log.Fatalf("sqlite: %v", err)
	}
	defer store.close()

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	awsCfg, err := awsconfig.LoadDefaultConfig(ctx, awsconfig.WithRegion(cfg.Region))
	if err != nil {
		log.Fatalf("aws: %v", err)
	}

	worker := &worker{
		cfg: cfg,
		db:  store,
		sqs: sqs.NewFromConfig(awsCfg),
		s3:  s3.NewFromConfig(awsCfg),
	}

	log.Printf("esperando mensagens em %s (concorrência %d)", cfg.QueueURL, cfg.Concurrency)

	if err := worker.run(ctx); err != nil && ctx.Err() == nil {
		log.Fatal(err)
	}

	log.Print("encerrado")
}

type worker struct {
	cfg config
	db  *store
	sqs *sqs.Client
	s3  *s3.Client
}

func (w *worker) run(ctx context.Context) error {
	slots := newLimiter(w.cfg.Concurrency)
	defer slots.wait()

	for {
		if err := slots.acquire(ctx); err != nil {
			return err
		}

		out, err := w.sqs.ReceiveMessage(ctx, &sqs.ReceiveMessageInput{
			QueueUrl:            aws.String(w.cfg.QueueURL),
			MaxNumberOfMessages: 1,
			WaitTimeSeconds:     20,
			VisibilityTimeout:   60,
		})
		if err != nil {
			slots.release()
			if ctx.Err() != nil {
				return ctx.Err()
			}
			log.Printf("receber mensagem: %v", err)
			continue
		}

		if len(out.Messages) == 0 {
			slots.release()
			continue
		}

		msg := out.Messages[0]
		jobCtx := context.WithoutCancel(ctx)
		slots.goWork(func() {
			w.handle(jobCtx, msg)
		})
	}
}

func (w *worker) handle(ctx context.Context, msg types.Message) {
	body := aws.ToString(msg.Body)
	receipt := aws.ToString(msg.ReceiptHandle)

	var job jobMessage
	if err := json.Unmarshal([]byte(body), &job); err != nil {
		log.Printf("mensagem inválida, descartando: %v (%s)", err, body)
		w.delete(ctx, receipt)
		return
	}

	if job.DocumentID == 0 || job.S3Key == "" {
		log.Printf("mensagem incompleta, descartando: %s", body)
		w.delete(ctx, receipt)
		return
	}

	log.Printf("documento %d chave %s", job.DocumentID, job.S3Key)

	if err := w.process(ctx, job); err != nil {
		log.Printf("documento %d falhou: %v", job.DocumentID, err)
		if dbErr := w.db.markFailed(job.DocumentID, err.Error()); dbErr != nil {
			log.Printf("não deu para marcar failed: %v", dbErr)
		}
	}

	w.delete(ctx, receipt)
}

func (w *worker) process(ctx context.Context, job jobMessage) error {
	doc, err := w.db.load(job.DocumentID)
	if err != nil {
		return fmt.Errorf("carregar documento: %w", err)
	}

	if err := w.db.markProcessing(job.DocumentID); err != nil {
		return fmt.Errorf("marcar processing: %w", err)
	}

	data, err := w.download(ctx, job.S3Key)
	if err != nil {
		return err
	}

	mime := detectMIME(data, doc.MIMEType)
	hash := sha256Hex(data)
	pages := pageCount(data, mime, doc.OriginalFilename)

	if err := w.db.markCompleted(job.DocumentID, mime, int64(len(data)), hash, pages); err != nil {
		return fmt.Errorf("marcar completed: %w", err)
	}

	log.Printf("documento %d concluído sha256=%s", job.DocumentID, hash)
	return nil
}

func (w *worker) download(ctx context.Context, key string) ([]byte, error) {
	out, err := w.s3.GetObject(ctx, &s3.GetObjectInput{
		Bucket: aws.String(w.cfg.Bucket),
		Key:    aws.String(key),
	})
	if err != nil {
		return nil, fmt.Errorf("baixar s3://%s/%s: %w", w.cfg.Bucket, key, err)
	}
	defer out.Body.Close()

	data, err := io.ReadAll(out.Body)
	if err != nil {
		return nil, fmt.Errorf("ler objeto S3: %w", err)
	}

	return data, nil
}

func (w *worker) delete(ctx context.Context, receipt string) {
	if receipt == "" {
		return
	}

	_, err := w.sqs.DeleteMessage(ctx, &sqs.DeleteMessageInput{
		QueueUrl:      aws.String(w.cfg.QueueURL),
		ReceiptHandle: aws.String(receipt),
	})
	if err != nil {
		log.Printf("apagar mensagem: %v", err)
	}
}
