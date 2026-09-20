<?php

namespace App\Services;

class DocumentProcessor
{
    /**
     * @return array{mime_type: string, size_bytes: int, sha256: string, page_count: int|null}
     */
    public function process(string $absolutePath, ?string $mimeType, string $originalFilename): array
    {
        if (! is_file($absolutePath)) {
            throw new \RuntimeException('Arquivo não encontrado para processamento.');
        }

        $mime = $mimeType ?: (mime_content_type($absolutePath) ?: 'application/octet-stream');
        $size = filesize($absolutePath);

        if ($size === false) {
            throw new \RuntimeException('Não foi possível ler o tamanho do arquivo.');
        }

        $hash = hash_file('sha256', $absolutePath);

        if ($hash === false) {
            throw new \RuntimeException('Não foi possível calcular o hash do arquivo.');
        }

        return [
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => $hash,
            'page_count' => $this->pageCount($absolutePath, $mime, $originalFilename),
        ];
    }

    private function pageCount(string $absolutePath, string $mimeType, string $originalFilename): ?int
    {
        $isPdf = $mimeType === 'application/pdf'
            || str_ends_with(strtolower($originalFilename), '.pdf');

        if (! $isPdf) {
            return null;
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new \RuntimeException('Não foi possível ler o PDF.');
        }

        preg_match_all('/\/Type\s*\/Page(?!s)\b/', $contents, $matches);

        return count($matches[0]);
    }
}
