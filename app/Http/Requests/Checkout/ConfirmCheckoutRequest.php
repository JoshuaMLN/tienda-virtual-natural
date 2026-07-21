<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConfirmCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'review_reference' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'idempotency_key' => ['required', 'uuid'],
            'accepted_proposal_reference' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'review_reference.required' => 'La referencia de revision es obligatoria.',
            'review_reference.regex' => 'La referencia de revision no es valida.',
            'idempotency_key.required' => 'La clave del intento de confirmacion es obligatoria.',
            'idempotency_key.uuid' => 'La clave del intento de confirmacion no es valida.',
            'accepted_proposal_reference.regex' => 'La propuesta aceptada no es valida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_reference' => strtolower(trim((string) $this->input('review_reference'))),
            'idempotency_key' => strtolower(trim((string) $this->input('idempotency_key'))),
            'accepted_proposal_reference' => $this->filled('accepted_proposal_reference')
                ? strtolower(trim((string) $this->input('accepted_proposal_reference')))
                : null,
        ]);
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'La solicitud de confirmacion no es valida.',
            'errors' => $validator->errors()->toArray(),
            'revalidation' => null,
        ], 422));
    }
}
