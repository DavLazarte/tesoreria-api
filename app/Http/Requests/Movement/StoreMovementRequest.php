<?php

namespace App\Http\Requests\Movement;

use App\Enums\MovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(MovementType::class)],
            'movement_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'concept' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'entity_id' => ['nullable', 'exists:entities,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de movimiento es obligatorio.',
            'movement_date.required' => 'La fecha del movimiento es obligatoria.',
            'movement_date.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un número válido.',
            'amount.gt' => 'El monto debe ser mayor a cero.',
            'concept.required' => 'El concepto o descripción es obligatorio.',
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'entity_id.exists' => 'La entidad seleccionada no existe.',
        ];
    }
}
