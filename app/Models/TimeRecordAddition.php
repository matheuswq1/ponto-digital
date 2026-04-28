<?php

namespace App\Models;

use App\Services\WorkDayService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeRecordAddition extends Model
{
    protected $fillable = [
        'employee_id',
        'requested_by',
        'type',
        'datetime',
        'justification',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'time_record_id',
    ];

    protected function casts(): array
    {
        return [
            'datetime'   => 'datetime',
            'approved_at'=> 'datetime',
        ];
    }

    /**
     * Datetimes no banco estão em hora local (BRT) — lê sem conversão de fuso.
     */
    protected function asDateTime($value): Carbon
    {
        if ($value instanceof Carbon) return $value->copy();
        if ($value instanceof \DateTimeInterface) return Carbon::instance($value);
        $tz = config('app.timezone', 'America/Sao_Paulo');
        return Carbon::createFromFormat('Y-m-d H:i:s', $value, $tz);
    }

    public function getDatetimeLocalAttribute(): ?Carbon
    {
        return $this->datetime; // já é hora local
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }
    public function timeRecord(): BelongsTo { return $this->belongsTo(TimeRecord::class); }

    public function isPending(): bool { return $this->status === 'pendente'; }

    /**
     * Aprova a solicitação: cria o TimeRecord real e recalcula o WorkDay.
     */
    public function approve(User $approver, ?string $notes = null): void
    {
        $this->update([
            'status'       => 'aprovado',
            'approved_by'  => $approver->id,
            'approved_at'  => now(),
            'approval_notes' => $notes,
        ]);

        $record = TimeRecord::create([
            'employee_id' => $this->employee_id,
            'type'        => $this->type,
            'datetime'    => $this->datetime,
            'ip_address'  => null,
            'device_info' => 'manual_addition',
            'is_mock_location' => false,
            'offline'     => false,
            'status'      => 'aprovado',
            'is_edited'   => true,
        ]);

        $this->update(['time_record_id' => $record->id]);

        $tz   = config('app.timezone', 'America/Sao_Paulo');
        $date = $this->datetime->copy()->setTimezone($tz)->toDateString();
        app(WorkDayService::class)->calculateAndSave($this->employee, $date);
    }

    public function reject(User $approver, string $notes): void
    {
        $this->update([
            'status'         => 'rejeitado',
            'approved_by'    => $approver->id,
            'approved_at'    => now(),
            'approval_notes' => $notes,
        ]);
    }
}
