<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeRecordEditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'time_record_id' => $this->time_record_id,
            'original' => [
                'datetime' => $this->original_datetime?->format('Y-m-d\TH:i:s'),
                'type' => $this->original_type,
            ],
            'new' => [
                'datetime' => $this->new_datetime?->format('Y-m-d\TH:i:s'),
                'type' => $this->new_type,
            ],
            'justification' => $this->justification,
            'status' => $this->status,
            'editor' => $this->whenLoaded('editor', fn() => [
                'id' => $this->editor->id,
                'name' => $this->editor->name,
            ]),
            'approver' => $this->whenLoaded('approver', fn() => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'approved_at' => $this->approved_at?->toISOString(),
            'approval_notes' => $this->approval_notes,
            'time_record' => $this->whenLoaded('timeRecord', fn() => new TimeRecordResource($this->timeRecord)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
