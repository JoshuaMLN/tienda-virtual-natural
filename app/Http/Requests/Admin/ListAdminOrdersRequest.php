<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'estado_pedido' => ['nullable', Rule::enum(OrderStatus::class)],
            'estado_pago' => ['nullable', Rule::enum(PaymentStatus::class)],
            'estado_entrega' => ['nullable', Rule::enum(DeliveryStatus::class)],
            'modalidad' => ['nullable', Rule::enum(DeliveryMethod::class)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.max' => 'La busqueda no debe superar los 100 caracteres.',
            'estado_pedido.enum' => 'El estado de pedido seleccionado no es valido.',
            'estado_pago.enum' => 'El estado de pago seleccionado no es valido.',
            'estado_entrega.enum' => 'El estado de entrega seleccionado no es valido.',
            'modalidad.enum' => 'La modalidad seleccionada no es valida.',
            'desde.date_format' => 'La fecha inicial no es valida.',
            'hasta.date_format' => 'La fecha final no es valida.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = trim((string) $this->input('q', ''));

        $this->merge([
            'q' => $search !== '' ? $search : null,
            'estado_pedido' => $this->filled('estado_pedido') ? $this->input('estado_pedido') : null,
            'estado_pago' => $this->filled('estado_pago') ? $this->input('estado_pago') : null,
            'estado_entrega' => $this->filled('estado_entrega') ? $this->input('estado_entrega') : null,
            'modalidad' => $this->filled('modalidad') ? $this->input('modalidad') : null,
            'desde' => $this->filled('desde') ? $this->input('desde') : null,
            'hasta' => $this->filled('hasta') ? $this->input('hasta') : null,
        ]);
    }
}
