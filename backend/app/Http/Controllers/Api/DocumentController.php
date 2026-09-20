<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class DocumentController extends Controller
{
    public function __construct(private DocumentQueue $queue) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Document::query()->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $validated['file'];

        $document = Document::create([
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => Document::STATUS_PENDING,
        ]);

        try {
            $path = $file->store('documents', 's3');

            if ($path === false) {
                throw new \RuntimeException('Falha ao enviar o arquivo para o S3.');
            }

            $document->update([
                'storage_path' => $path,
            ]);

            $this->queue->publish($document->refresh());
        } catch (Throwable $exception) {
            $document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);
        }

        return response()->json($document->refresh(), 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($document);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Document::STATUSES)],
        ]);

        $document->update($validated);

        return response()->json($document);
    }
}
