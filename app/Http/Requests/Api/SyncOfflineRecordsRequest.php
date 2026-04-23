<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncOfflineRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'records' => 'required|array|min:1|max:50',
            'records.*.type' => 'required|in:entrada,saida_almoco,volta_almoco,saida',
            'records.*.datetime' => 'required|date',
            'records.*.latitude' => 'nullable|numeric|between:-90,90',
            'records.*.longitude' => 'nullable|numeric|between:-180,180',
            'records.*.accuracy' => 'nullable|numeric|min:0',
            'records.*.photo_url' => 'nullable|url',
            'records.*.device_id' => 'nullable|string|max:255',
            'records.*.is_mock_location' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'records.required' => 'Nenhum registro para sincronizar.',
            'records.*.type.required' => 'Tipo de ponto obrigatório.',
            'records.*.datetime.required' => 'Data/hora obrigatória.',
        ];
    }
}
