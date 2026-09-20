<?php

namespace Tests\Unit;

use App\Services\DocumentProcessor;
use PHPUnit\Framework\TestCase;

class DocumentProcessorTest extends TestCase
{
    public function test_it_extracts_hash_size_and_mime_from_text(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, 'hello');

        $result = (new DocumentProcessor)->process($path, 'text/plain', 'hello.txt');

        $this->assertSame('text/plain', $result['mime_type']);
        $this->assertSame(5, $result['size_bytes']);
        $this->assertSame(hash('sha256', 'hello'), $result['sha256']);
        $this->assertNull($result['page_count']);

        unlink($path);
    }

    public function test_it_counts_pages_in_a_simple_pdf(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $this->onePagePdf());

        $result = (new DocumentProcessor)->process($path, 'application/pdf', 'aula.pdf');

        $this->assertSame(1, $result['page_count']);

        unlink($path);
    }

    private function onePagePdf(): string
    {
        return <<<'PDF'
        %PDF-1.1
        1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
        2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
        3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 3 3]>>endobj
        trailer<</Root 1 0 R>>
        %%EOF
        PDF;
    }
}
