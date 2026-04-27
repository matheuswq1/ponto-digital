<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'datetime' => $this->datetime?->format('Y-m-d\TH:i:s'), // hora local armazenada sem fuso
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'accuracy' => $this->accuracy,
            ],
            'photo_url' => $this->photo_url,
            'device_info' => $this->device_info,
            'is_mock_location' => $this->is_mock_location,
            'offline' => $this->offline,
            'synced_at' => $this->synced_at?->toISOString(),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'is_edited' => $this->is_edited,
            'has_pending_edit' => $this->edits()->where('status', 'pendente')->exists(),
            'edits' => $this->whenLoaded('edits', fn() => TimeRecordEditResource::collection($this->edits)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
