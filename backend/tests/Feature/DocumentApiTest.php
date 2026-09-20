<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_documents(): void
    {
        Document::create(['original_filename' => 'a.pdf']);
        Document::create(['original_filename' => 'b.pdf']);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['original_filename' => 'a.pdf']);
    }

    public function test_it_creates_a_document_as_pending(): void
    {
        $this->postJson('/api/documents', [
            'original_filename' => 'contrato.pdf',
        ])
            ->assertCreated()
            ->assertJsonPath('original_filename', 'contrato.pdf')
            ->assertJsonPath('status', Document::STATUS_PENDING)
            ->assertJsonPath('sha256', null);

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'contrato.pdf',
            'status' => Document::STATUS_PENDING,
        ]);
    }

    public function test_it_requires_original_filename(): void
    {
        $this->postJson('/api/documents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['original_filename']);
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
}
