<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => [
                Rule::requiredIf($this->routeIs('admin.orders.cancel')),
                'nullable',
                'string',
                'min:5',
                'max:255',
            ],
            'return' => ['nullable', 'array'],
            'return.q' => ['nullable', 'string', 'max:120'],
            'return.estado_pedido' => ['nullable', Rule::enum(OrderStatus::class)],
            'return.estado_pago' => ['nullable', Rule::enum(PaymentStatus::class)],
            'return.estado_entrega' => ['nullable', Rule::enum(DeliveryStatus::class)],
            'return.modalidad' => ['nullable', Rule::enum(DeliveryMethod::class)],
            'return.desde' => ['nullable', 'date_format:Y-m-d'],
            'return.hasta' => ['nullable', 'date_format:Y-m-d'],
            'return.page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Debes indicar el motivo de la cancelacion.',
            'reason.min' => 'El motivo debe tener al menos :min caracteres.',
            'reason.max' => 'El motivo no puede superar los :max caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason')) {
            $this->merge(['reason' => trim((string) $this->input('reason'))]);
        }
    }
}
