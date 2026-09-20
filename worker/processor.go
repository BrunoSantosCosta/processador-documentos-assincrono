package main

import (
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"regexp"
	"strings"
)

var pageObject = regexp.MustCompile(`/Type\s*/Page`)

func sha256Hex(data []byte) string {
	sum := sha256.Sum256(data)
	return hex.EncodeToString(sum[:])
}

func detectMIME(data []byte, fallback string) string {
	detected := http.DetectContentType(data)
	if detected == "" || detected == "application/octet-stream" {
		if fallback != "" {
			return fallback
		}
		return "application/octet-stream"
	}
	return detected
}

func isPDF(mimeType, originalFilename string) bool {
	return mimeType == "application/pdf" || strings.HasSuffix(strings.ToLower(originalFilename), ".pdf")
}

// pageCount mirrors the PHP regex /Type /Page (not /Pages).
// Go's regexp engine does not support lookahead, so we skip a match
// when the next character is 's'.
func pageCount(data []byte, mimeType, originalFilename string) *int {
	if !isPDF(mimeType, originalFilename) {
		return nil
	}

	content := string(data)
	count := 0
	for _, loc := range pageObject.FindAllStringIndex(content, -1) {
		end := loc[1]
		if end < len(content) && content[end] == 's' {
			continue
		}
		count++
	}

	return &count
}
