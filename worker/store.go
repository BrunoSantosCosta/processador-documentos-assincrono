package main

import (
	"database/sql"
	"fmt"
	"time"
)

type store struct {
	db *sql.DB
}

type documentRow struct {
	ID               int64
	OriginalFilename string
	MIMEType         string
}

func openStore(path string) (*store, error) {
	db, err := sql.Open("sqlite", path+"?_pragma=busy_timeout(5000)&_pragma=journal_mode(WAL)")
	if err != nil {
		return nil, err
	}

	db.SetMaxOpenConns(1)

	if err := db.Ping(); err != nil {
		_ = db.Close()
		return nil, err
	}

	return &store{db: db}, nil
}

func (s *store) close() error {
	return s.db.Close()
}

func (s *store) load(id int64) (documentRow, error) {
	var row documentRow
	var mime sql.NullString

	err := s.db.QueryRow(
		`SELECT id, original_filename, mime_type FROM documents WHERE id = ?`,
		id,
	).Scan(&row.ID, &row.OriginalFilename, &mime)
	if err != nil {
		return documentRow{}, err
	}

	row.MIMEType = mime.String
	return row, nil
}

func (s *store) markProcessing(id int64) error {
	result, err := s.db.Exec(
		`UPDATE documents SET status = ?, error_message = NULL, updated_at = ? WHERE id = ?`,
		"processing",
		nowUTC(),
		id,
	)
	if err != nil {
		return err
	}

	n, err := result.RowsAffected()
	if err != nil {
		return err
	}
	if n == 0 {
		return fmt.Errorf("documento %d não encontrado", id)
	}

	return nil
}

func (s *store) markCompleted(id int64, mime string, size int64, hash string, pages *int) error {
	var page any
	if pages != nil {
		page = *pages
	}

	_, err := s.db.Exec(
		`UPDATE documents
		 SET status = ?, mime_type = ?, size_bytes = ?, sha256 = ?, page_count = ?,
		     error_message = NULL, processed_at = ?, updated_at = ?
		 WHERE id = ?`,
		"completed",
		mime,
		size,
		hash,
		page,
		nowUTC(),
		nowUTC(),
		id,
	)
	return err
}

func (s *store) markFailed(id int64, message string) error {
	_, err := s.db.Exec(
		`UPDATE documents SET status = ?, error_message = ?, updated_at = ? WHERE id = ?`,
		"failed",
		message,
		nowUTC(),
		id,
	)
	return err
}

func nowUTC() string {
	return time.Now().UTC().Format("2006-01-02 15:04:05")
}
