<?php

namespace Tests\Feature;

use App\Models\Document;
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

        Storage::fake('local');
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

    public function test_it_uploads_and_processes_a_text_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('notes.txt', 'hello');

        $this->post('/api/documents', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('original_filename', 'notes.txt')
            ->assertJsonPath('status', Document::STATUS_COMPLETED)
            ->assertJsonPath('size_bytes', 5)
            ->assertJsonPath('sha256', hash('sha256', 'hello'))
            ->assertJsonPath('page_count', null);

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'notes.txt',
            'status' => Document::STATUS_COMPLETED,
            'sha256' => hash('sha256', 'hello'),
        ]);

        Storage::disk('local')->assertExists(
            Document::query()->first()->storage_path
        );
    }

    public function test_it_counts_pages_when_uploading_a_pdf(): void
    {
        $file = UploadedFile::fake()->createWithContent('aula.pdf', $this->onePagePdf());

        $this->post('/api/documents', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', Document::STATUS_COMPLETED)
            ->assertJsonPath('page_count', 1);
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
