<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\ListingExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingExtractionController extends Controller
{
    public function __construct(private ListingExtractionService $extraction)
    {
    }

    public function extract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|min:20|max:5000',
        ]);

        try {
            $result = $this->extraction->extract($validated['text']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['extracted' => $result]);
    }
}
