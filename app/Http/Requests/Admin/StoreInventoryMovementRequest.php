<?php

namespace App\Http\Requests\Admin;

use App\Models\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    protected $errorBag = 'movement';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(InventoryMovement::TYPES)],
            'quantity' => ['nullable', 'required_unless:type,'.InventoryMovement::TYPE_ADJUSTMENT, 'integer', 'min:1'],
            'new_stock' => ['nullable', 'required_if:type,'.InventoryMovement::TYPE_ADJUSTMENT, 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'movement_product_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Selecciona el tipo de movimiento.',
            'type.in' => 'Selecciona un tipo de movimiento valido.',
            'quantity.required_unless' => 'Ingresa la cantidad del movimiento.',
            'quantity.integer' => 'La cantidad debe ser un numero entero.',
            'quantity.min' => 'La cantidad debe ser mayor que cero.',
            'new_stock.required_if' => 'Ingresa el stock final del ajuste.',
            'new_stock.integer' => 'El stock final debe ser un numero entero.',
            'new_stock.min' => 'El stock final no puede ser negativo.',
            'reason.required' => 'Ingresa el motivo del movimiento.',
            'reason.max' => 'El motivo no debe superar los 120 caracteres.',
            'reference.max' => 'La referencia no debe superar los 255 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo de movimiento',
            'quantity' => 'cantidad',
            'new_stock' => 'stock final',
            'reason' => 'motivo',
            'reference' => 'referencia',
            'notes' => 'notas',
        ];
    }
}
