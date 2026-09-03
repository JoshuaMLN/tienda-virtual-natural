<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminFulfillmentFilter;
use App\Enums\DeliveryAttemptAttribution;
use App\Enums\DeliveryAttemptResult;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operation_token' => ['required', 'uuid'],
            'result' => ['required', Rule::enum(DeliveryAttemptResult::class)],
            'attribution' => [
                Rule::requiredIf($this->input('result') === DeliveryAttemptResult::Incident->value),
                'nullable',
                Rule::enum(DeliveryAttemptAttribution::class),
            ],
            'responsible_name' => ['required', 'string', 'max:120'],
            'attempt_reason' => [
                Rule::requiredIf($this->input('result') === DeliveryAttemptResult::Incident->value),
                'nullable',
                'string',
                'min:5',
                'max:500',
            ],
            'occurred_at' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'return' => ['nullable', 'array'],
            'return.q' => ['nullable', 'string', 'max:120'],
            'return.estado_pedido' => ['nullable', Rule::enum(OrderStatus::class)],
            'return.estado_pago' => ['nullable', Rule::enum(PaymentStatus::class)],
            'return.estado_entrega' => ['nullable', Rule::enum(DeliveryStatus::class)],
            'return.modalidad' => ['nullable', Rule::enum(DeliveryMethod::class)],
            'return.seguimiento' => ['nullable', Rule::enum(AdminFulfillmentFilter::class)],
            'return.desde' => ['nullable', 'date_format:Y-m-d'],
            'return.hasta' => ['nullable', 'date_format:Y-m-d'],
            'return.page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'operation_token.required' => 'No se pudo identificar esta operacion. Recarga la pagina e intenta nuevamente.',
            'operation_token.uuid' => 'La referencia de la operacion no es valida.',
            'result.required' => 'Selecciona el resultado de la visita.',
            'result.enum' => 'El resultado seleccionado no es valido.',
            'attribution.required' => 'Indica a quien se atribuye la incidencia.',
            'attribution.enum' => 'La atribucion seleccionada no es valida.',
            'responsible_name.required' => 'Identifica al responsable o transportista.',
            'responsible_name.max' => 'El responsable no puede superar los :max caracteres.',
            'attempt_reason.required' => 'Describe el motivo de la incidencia.',
            'attempt_reason.min' => 'El motivo debe tener al menos :min caracteres.',
            'attempt_reason.max' => 'El motivo no puede superar los :max caracteres.',
            'occurred_at.required' => 'Indica cuando ocurrio la visita.',
            'occurred_at.date_format' => 'La fecha y hora de la visita no son validas.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'responsible_name' => trim((string) $this->input('responsible_name')),
            'attempt_reason' => $this->filled('attempt_reason')
                ? trim((string) $this->input('attempt_reason'))
                : null,
            'attribution' => $this->input('result') === DeliveryAttemptResult::Delivered->value
                ? DeliveryAttemptAttribution::Unattributed->value
                : $this->input('attribution'),
        ]);
    }
}
