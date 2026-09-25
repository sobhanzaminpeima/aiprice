<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitKycRequest;
use App\Models\KycVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    /**
     * The dashboard needs to distinguish "never submitted" from "waiting on
     * review" from "rejected" — returning the latest record plus the user's
     * verified flag covers all three without a second round trip.
     */
    public function show(Request $request): JsonResponse
    {
        $kyc = KycVerification::where('user_id', $request->user()->id)
            ->latest()
            ->first();

        return response()->json([
            'kyc' => $kyc,
            'status' => $kyc?->status ?? 'not_submitted',
            'verified' => (bool) $request->user()->kyc_verified,
        ]);
    }

    public function store(SubmitKycRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->kyc_verified) {
            return response()->json(['message' => 'Your identity is already verified.'], 422);
        }

        $pending = KycVerification::where('user_id', $user->id)->where('status', 'pending')->first();
        if ($pending) {
            return response()->json(['message' => 'A submission is already under review.'], 422);
        }

        $data = $request->validated();

        foreach (['document_front', 'document_back', 'selfie'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store("kyc/{$user->id}", 'local');
            }
        }

        $kyc = KycVerification::create($data + [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        return response()->json(['kyc' => $kyc, 'status' => 'pending'], 201);
    }

    /**
     * KYC documents are identity papers, so they are stored on the private
     * disk and streamed only to their owner or a KYC reviewer.
     */
    public function document(Request $request, string $id, string $field)
    {
        abort_unless(in_array($field, ['document_front', 'document_back', 'selfie'], true), 404);

        $kyc = KycVerification::findOrFail($id);
        abort_unless($kyc->user_id === $request->user()->id || $request->user()->can('approve_kyc'), 403);

        $path = $kyc->{$field};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
