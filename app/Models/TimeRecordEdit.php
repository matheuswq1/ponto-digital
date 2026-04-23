<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeRecordEdit extends Model
{
    use HasFactory;

    protected $fillable = [
        'time_record_id',
        'edited_by',
        'original_datetime',
        'new_datetime',
        'original_type',
        'new_type',
        'justification',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
    ];

    protected function casts(): array
    {
        return [
            'original_datetime' => 'datetime',
            'new_datetime' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function timeRecord(): BelongsTo
    {
        return $this->belongsTo(TimeRecord::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pendente';
    }

    public function approve(User $approver, ?string $notes = null): void
    {
        $this->update([
            'status' => 'aprovado',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);

        $this->timeRecord->update([
            'datetime' => $this->new_datetime,
            'type' => $this->new_type,
            'is_edited' => true,
        ]);
    }

    public function reject(User $approver, string $notes): void
    {
        $this->update([
            'status' => 'rejeitado',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }
}
