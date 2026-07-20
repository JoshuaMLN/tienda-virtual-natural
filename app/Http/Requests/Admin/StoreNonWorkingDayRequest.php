<?php

namespace App\Http\Requests\Admin;

use App\Models\NonWorkingDay;
use Illuminate\Foundation\Http\FormRequest;

class StoreNonWorkingDayRequest extends FormRequest
{
    protected $errorBag = 'nonWorkingDay';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (NonWorkingDay::query()->whereDate('date', (string) $value)->exists()) {
                        $fail('Esta fecha ya se encuentra registrada.');
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.required' => 'Selecciona la fecha sin atencion.',
            'date.date_format' => 'La fecha seleccionada no es valida.',
            'date.after_or_equal' => 'La fecha sin atencion no puede estar en el pasado.',
            'reason.max' => 'El motivo no debe superar los 120 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = trim((string) $this->input('reason'));

        $this->merge([
            'date' => trim((string) $this->input('date')),
            'reason' => $reason !== '' ? $reason : null,
        ]);
    }
}
