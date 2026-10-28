<?php

namespace App\Http\Requests\EntityType;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntityTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:entity_types,name'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del tipo de entidad es obligatorio.',
            'name.unique' => 'Ya existe un tipo de entidad con ese nombre.',
        ];
    }
}
