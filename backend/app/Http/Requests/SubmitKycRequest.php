<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_type' => 'required|in:passport,national_id,id_card,driving_license',
            'document_number' => 'required|string|max:64',
            'full_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'nationality' => 'nullable|string|max:64',
            'document_front' => 'nullable|image|max:8192',
            'document_back' => 'nullable|image|max:8192',
            'selfie' => 'nullable|image|max:8192',
        ];
    }
}
