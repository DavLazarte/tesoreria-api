<?php

namespace App\Http\Requests\Category;

use App\Enums\CategoryFlow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'flow' => ['required', new Enum(CategoryFlow::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'flow.required' => 'El tipo de flujo (ingreso o egreso) es obligatorio.',
        ];
    }
}
