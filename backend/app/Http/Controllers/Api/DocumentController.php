<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Document::query()->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_filename' => ['required', 'string', 'max:255'],
        ]);

        $document = Document::create($validated);
        $document->refresh();

        return response()->json($document, 201);
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
