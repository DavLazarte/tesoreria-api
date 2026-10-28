<?php

namespace App\Http\Requests\EntityType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntityTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('entity_type') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('entity_types', 'name')->ignore($id)],
            'is_active' => ['sometimes', 'boolean'],
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
