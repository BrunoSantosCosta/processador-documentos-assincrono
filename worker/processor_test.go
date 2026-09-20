package main

import (
	"testing"
)

func TestSHA256Hex(t *testing.T) {
	got := sha256Hex([]byte("hello"))
	want := "2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824"
	if got != want {
		t.Fatalf("sha256Hex() = %s, want %s", got, want)
	}
}

func TestPageCountPDF(t *testing.T) {
	pdf := []byte(`%PDF-1.1
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 3 3]>>endobj
trailer<</Root 1 0 R>>
%%EOF
`)

	got := pageCount(pdf, "application/pdf", "aula.pdf")
	if got == nil || *got != 1 {
		t.Fatalf("pageCount() = %v, want 1", got)
	}
}

func TestPageCountNotPDF(t *testing.T) {
	got := pageCount([]byte("hello"), "text/plain", "notes.txt")
	if got != nil {
		t.Fatalf("pageCount() = %v, want nil", got)
	}
}
