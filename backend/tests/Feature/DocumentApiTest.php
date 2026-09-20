<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Services\DocumentQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->mock(DocumentQueue::class, function ($mock) {
            $mock->shouldReceive('publish')->byDefault();
        });
    }

    public function test_it_lists_documents(): void
    {
        Document::create(['original_filename' => 'a.pdf']);
        Document::create(['original_filename' => 'b.pdf']);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['original_filename' => 'a.pdf']);
    }

    public function test_it_uploads_to_s3_and_stays_pending(): void
    {
        $this->mock(DocumentQueue::class, function ($mock) {
            $mock->shouldReceive('publish')
                ->once()
                ->withArgs(function (Document $document) {
                    return $document->original_filename === 'notes.txt'
                        && is_string($document->storage_path)
                        && str_starts_with($document->storage_path, 'documents/');
                });
        });

        $file = UploadedFile::fake()->createWithContent('notes.txt', 'hello');

        $this->post('/api/documents', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('original_filename', 'notes.txt')
            ->assertJsonPath('status', Document::STATUS_PENDING)
            ->assertJsonPath('size_bytes', 5)
            ->assertJsonPath('sha256', null)
            ->assertJsonPath('page_count', null)
            ->assertJsonPath('processed_at', null)
            ->assertJsonPath('storage_path', fn ($path) => is_string($path) && str_starts_with($path, 'documents/'));

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'notes.txt',
            'status' => Document::STATUS_PENDING,
            'size_bytes' => 5,
            'sha256' => null,
        ]);

        Storage::disk('s3')->assertExists(
            Document::query()->first()->storage_path
        );
    }

    public function test_it_does_not_extract_pdf_pages_on_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('aula.pdf', $this->onePagePdf());

        $this->post('/api/documents', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', Document::STATUS_PENDING)
            ->assertJsonPath('page_count', null)
            ->assertJsonPath('sha256', null);
    }

    public function test_it_marks_failed_when_queue_publish_fails(): void
    {
        $this->mock(DocumentQueue::class, function ($mock) {
            $mock->shouldReceive('publish')
                ->once()
                ->andThrow(new \RuntimeException('fila indisponível'));
        });

        $file = UploadedFile::fake()->createWithContent('notes.txt', 'hello');

        $this->post('/api/documents', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', Document::STATUS_FAILED)
            ->assertJsonPath('error_message', 'fila indisponível');

        Storage::disk('s3')->assertExists(
            Document::query()->first()->storage_path
        );
    }

    public function test_it_requires_a_file(): void
    {
        $this->postJson('/api/documents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_it_shows_a_document(): void
    {
        $document = Document::create(['original_filename' => 'aula.pdf']);

        $this->getJson("/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('id', $document->id)
            ->assertJsonPath('original_filename', 'aula.pdf');
    }

    public function test_it_returns_not_found_for_unknown_document(): void
    {
        $this->getJson('/api/documents/999')
            ->assertNotFound();
    }

    public function test_it_updates_status(): void
    {
        $document = Document::create(['original_filename' => 'aula.pdf']);

        $this->patchJson("/api/documents/{$document->id}", [
            'status' => Document::STATUS_COMPLETED,
        ])
            ->assertOk()
            ->assertJsonPath('status', Document::STATUS_COMPLETED);
    }

    public function test_it_rejects_invalid_status(): void
    {
        $document = Document::create(['original_filename' => 'aula.pdf']);

        $this->patchJson("/api/documents/{$document->id}", [
            'status' => 'banana',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_it_does_not_change_filename_via_patch(): void
    {
        $document = Document::create(['original_filename' => 'aula.pdf']);

        $this->patchJson("/api/documents/{$document->id}", [
            'status' => Document::STATUS_PROCESSING,
            'original_filename' => 'hacked.pdf',
        ])
            ->assertOk()
            ->assertJsonPath('original_filename', 'aula.pdf');
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
