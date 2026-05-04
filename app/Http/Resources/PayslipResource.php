<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'reference_month' => $this->reference_month,
            'reference_year'  => $this->reference_year,
            'reference_label' => $this->getReferenceLabel(),
            'file_url'        => $this->file_url,
            'file_name'       => $this->file_name,
            'file_size'       => $this->file_size,
            'file_size_label' => $this->getFileSizeFormatted(),
            'description'     => $this->description,
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
