package main

import (
	"fmt"
	"os"
	"strconv"

	"github.com/joho/godotenv"
)

type config struct {
	Region      string
	Bucket      string
	QueueURL    string
	SQLitePath  string
	Concurrency int
}

func loadConfig() (config, error) {
	_ = godotenv.Load(".env")
	_ = godotenv.Load("../backend/.env")

	concurrency, err := concurrencyFromEnv()
	if err != nil {
		return config{}, err
	}

	cfg := config{
		Region:      firstNonEmpty(os.Getenv("AWS_DEFAULT_REGION"), "us-east-1"),
		Bucket:      os.Getenv("AWS_BUCKET"),
		QueueURL:    os.Getenv("AWS_SQS_QUEUE_URL"),
		SQLitePath:  firstNonEmpty(os.Getenv("SQLITE_PATH"), "../backend/database/database.sqlite"),
		Concurrency: concurrency,
	}

	if os.Getenv("AWS_ACCESS_KEY_ID") == "" || os.Getenv("AWS_SECRET_ACCESS_KEY") == "" {
		return config{}, fmt.Errorf("AWS_ACCESS_KEY_ID e AWS_SECRET_ACCESS_KEY são obrigatórias")
	}
	if cfg.Bucket == "" {
		return config{}, fmt.Errorf("AWS_BUCKET é obrigatória")
	}
	if cfg.QueueURL == "" {
		return config{}, fmt.Errorf("AWS_SQS_QUEUE_URL é obrigatória")
	}

	return cfg, nil
}

func concurrencyFromEnv() (int, error) {
	raw := os.Getenv("WORKER_CONCURRENCY")
	if raw == "" {
		return 3, nil
	}

	n, err := strconv.Atoi(raw)
	if err != nil || n < 1 {
		return 0, fmt.Errorf("WORKER_CONCURRENCY deve ser um inteiro >= 1")
	}

	return n, nil
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if value != "" {
			return value
		}
	}
	return ""
}
