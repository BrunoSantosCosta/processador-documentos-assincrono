<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class DocumentController extends Controller
{
    public function __construct(private DocumentProcessor $processor)
    {
    }

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
            'status' => Document::STATUS_PROCESSING,
        ]);

        try {
            $path = $file->store('documents');

            if ($path === false) {
                throw new \RuntimeException('Falha ao gravar o arquivo no disco.');
            }

            $result = $this->processor->process(
                Storage::path($path),
                $file->getMimeType(),
                $file->getClientOriginalName(),
            );

            $document->update([
                ...$result,
                'storage_path' => $path,
                'status' => Document::STATUS_COMPLETED,
                'processed_at' => now(),
                'error_message' => null,
            ]);
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
