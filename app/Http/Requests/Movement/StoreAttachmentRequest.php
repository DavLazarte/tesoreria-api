<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'], // hasta 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Es obligatorio seleccionar un archivo.',
            'file.file' => 'El archivo no es válido.',
            'file.mimes' => 'El comprobante debe ser un archivo PDF o una imagen (JPG, PNG, WEBP).',
            'file.max' => 'El archivo no puede superar los 10 megabytes.',
        ];
    }
}
