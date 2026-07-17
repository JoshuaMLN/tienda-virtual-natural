<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:100', 'max:50000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Ingresa el titulo del documento.',
            'body.required' => 'Ingresa el contenido legal.',
            'body.min' => 'El contenido legal debe tener al menos 100 caracteres.',
            'body.max' => 'El contenido legal no debe superar 50000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'body' => trim((string) $this->input('body')),
        ]);
    }
}
