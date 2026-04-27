<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:entrada,saida',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photo_url' => 'nullable|url',
            'device_id' => 'nullable|string|max:255',
            'is_mock_location' => ['nullable', 'in:0,1,true,false'],
            'offline' => ['nullable', 'in:0,1,true,false'],
            'wifi_ssid' => 'nullable|string|max:64',
            'wifi_connected' => ['nullable', 'in:0,1,true,false'],
            'speed_kmh' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo de ponto é obrigatório.',
            'type.in' => 'Tipo de ponto inválido.',
            'photo.image' => 'O arquivo deve ser uma imagem.',
            'photo.max' => 'A foto deve ter no máximo 5MB.',
        ];
    }
}
