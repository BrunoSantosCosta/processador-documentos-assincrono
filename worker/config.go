package main

import (
	"fmt"
	"os"

	"github.com/joho/godotenv"
)

type config struct {
	Region     string
	Bucket     string
	QueueURL   string
	SQLitePath string
}

func loadConfig() (config, error) {
	_ = godotenv.Load(".env")
	_ = godotenv.Load("../backend/.env")

	cfg := config{
		Region:     firstNonEmpty(os.Getenv("AWS_DEFAULT_REGION"), "us-east-1"),
		Bucket:     os.Getenv("AWS_BUCKET"),
		QueueURL:   os.Getenv("AWS_SQS_QUEUE_URL"),
		SQLitePath: firstNonEmpty(os.Getenv("SQLITE_PATH"), "../backend/database/database.sqlite"),
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

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if value != "" {
			return value
		}
	}
	return ""
}
