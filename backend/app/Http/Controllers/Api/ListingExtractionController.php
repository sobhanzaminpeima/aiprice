<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\ListingDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingExtractionController extends Controller
{
    public function __construct(private ListingDraftService $extraction)
    {
    }

    public function extract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|min:20|max:12000',
            'model' => ['nullable', 'string', \Illuminate\Validation\Rule::in(config('services.runcrate.models', []))],
        ]);

        try {
            $result = $this->extraction->extract($validated['text'], $validated['model'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['extracted' => $result]);
    }
}
