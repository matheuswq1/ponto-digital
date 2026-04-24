<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HourBankRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'requested_date',
        'minutes_requested',
        'justification',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'approved_at'    => 'datetime',
            'minutes_requested' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(HourBankTransaction::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pendente';
    }

    public function isApproved(): bool
    {
        return $this->status === 'aprovado';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejeitado';
    }

    public function approve(User $user, ?string $notes = null): void
    {
        $this->update([
            'status'         => 'aprovado',
            'approved_by'    => $user->id,
            'approved_at'    => Carbon::now(),
            'approval_notes' => $notes,
        ]);

        HourBankTransaction::create([
            'employee_id'           => $this->employee_id,
            'hour_bank_request_id'  => $this->id,
            'type'                  => 'folga_aprovada',
            'minutes'               => -abs($this->minutes_requested),
            'description'           => 'Folga compensatória aprovada para ' . $this->requested_date->format('d/m/Y'),
            'reference_date'        => $this->requested_date,
            'created_by'            => $user->id,
        ]);
    }

    public function reject(User $user, string $notes): void
    {
        $this->update([
            'status'         => 'rejeitado',
            'approved_by'    => $user->id,
            'approved_at'    => Carbon::now(),
            'approval_notes' => $notes,
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pendente'  => 'Pendente',
            'aprovado'  => 'Aprovado',
            'rejeitado' => 'Rejeitado',
            default     => $this->status,
        };
    }

    public function getRequestedHoursAttribute(): string
    {
        $abs  = abs($this->minutes_requested);
        $h    = intdiv($abs, 60);
        $m    = $abs % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
}
