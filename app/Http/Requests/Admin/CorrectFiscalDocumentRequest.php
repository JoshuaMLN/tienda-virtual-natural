<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class CorrectFiscalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'pdf' => ['nullable', File::types(['pdf'])->max('10mb')],
            'series' => ['required', 'string', 'max:10'],
            'correlative' => ['required', 'string', 'max:20'],
            'issued_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'issued_at.before_or_equal' => 'La fecha de emision no puede ser futura.',
            'pdf.required' => 'Debes adjuntar el PDF oficial del comprobante.',
            'pdf.file' => 'El comprobante debe ser un archivo valido.',
            'pdf.mimetypes' => 'El comprobante debe estar en formato PDF.',
            'pdf.max' => 'El PDF no puede superar los 10 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'series' => strtoupper(trim((string) $this->input('series'))),
            'correlative' => trim((string) $this->input('correlative')),
        ]);
    }
}
