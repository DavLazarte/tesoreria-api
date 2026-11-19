<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountabilityRequest extends FormRequest
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
            'rendered_at' => ['required', 'date_format:Y-m-d'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'min:3', 'max:5000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rendered_at.required' => 'La fecha de rendición es obligatoria.',
            'rendered_at.date_format' => 'La fecha de rendición debe tener el formato AAAA-MM-DD.',
            'amount.numeric' => 'El importe rendido debe ser un número válido.',
            'amount.gt' => 'El importe rendido debe ser mayor a cero.',
            'description.required' => 'El detalle explicativo de la rendición es obligatorio.',
            'description.min' => 'El detalle debe tener al menos 3 caracteres.',
            'files.array' => 'Los comprobantes deben enviarse en formato lista.',
            'files.max' => 'No se pueden subir más de 10 comprobantes por rendición.',
            'files.*.file' => 'Uno de los comprobantes adjuntos no es un archivo válido.',
            'files.*.mimes' => 'Los comprobantes deben ser archivos PDF o imágenes (JPG, PNG, WEBP).',
            'files.*.max' => 'Cada comprobante no puede superar los 10 megabytes.',
        ];
    }
}
