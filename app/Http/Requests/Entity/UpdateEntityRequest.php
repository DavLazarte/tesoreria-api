<?php

namespace App\Http\Requests\Entity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntityRequest extends FormRequest
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
            'entity_type_id' => ['sometimes', 'required', 'exists:entity_types,id'],
            'group_id' => ['nullable', 'exists:funds,id'],
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entity_type_id.required' => 'El tipo de entidad es obligatorio.',
            'entity_type_id.exists' => 'El tipo de entidad seleccionado no existe.',
            'name.required' => 'El nombre de la entidad es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
        ];
    }
}
