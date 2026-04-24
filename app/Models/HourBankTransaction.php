<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HourBankTransaction extends Model
{
    protected $fillable = [
        'employee_id',
        'work_day_id',
        'hour_bank_request_id',
        'type',
        'minutes',
        'description',
        'reference_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
            'minutes'        => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workDay(): BelongsTo
    {
        return $this->belongsTo(WorkDay::class);
    }

    public function hourBankRequest(): BelongsTo
    {
        return $this->belongsTo(HourBankRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'extra'          => 'Hora extra',
            'deficit'        => 'Saída antecipada',
            'folga_aprovada' => 'Folga compensatória',
            'ajuste_manual'  => 'Ajuste manual',
            default          => $this->type,
        };
    }

    public function isCredit(): bool
    {
        return $this->minutes > 0;
    }

    public function isDebit(): bool
    {
        return $this->minutes < 0;
    }
}
