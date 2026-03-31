<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AssetDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'documentable_type' => ['required', 'string', Rule::in(array_keys(AssetDocument::documentableMap()))],
            'documentable_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(config('fleet.asset_document.types'))],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'status' => ['required', Rule::in(config('fleet.asset_document.statuses'))],
            'metadata' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'file' => [
                'nullable',
                'file',
                'max:'.config('fleet.asset_document.max_upload_kb', 10240),
                'mimes:'.implode(',', config('fleet.asset_document.allowed_extensions', ['pdf'])),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $documentableType = $this->input('documentable_type');
            $documentableId = $this->input('documentable_id');

            if (! $documentableType || ! $documentableId) {
                return;
            }

            $documentableClass = AssetDocument::resolveDocumentableClass($documentableType);

            if (! $documentableClass || ! class_exists($documentableClass)) {
                $validator->errors()->add('documentable_type', 'The selected document target is invalid.');

                return;
            }

            $exists = $documentableClass::query()->whereKey($documentableId)->exists();

            if (! $exists) {
                $validator->errors()->add('documentable_id', 'The selected document target could not be found.');
            }
        });
    }
}
