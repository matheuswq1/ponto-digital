<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayPeriodClosure extends Model
{
    protected $fillable = [
        'company_id',
        'period_start',
        'period_end',
        'notes',
        'closed_at',
        'closed_by',
        'corrected_from_closure_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(EmployeePayPeriodAcknowledgement::class);
    }

    public function correctedFromClosure(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrected_from_closure_id');
    }

    public function correctionClosures(): HasMany
    {
        return $this->hasMany(self::class, 'corrected_from_closure_id');
    }

    public function isCorrection(): bool
    {
        return $this->corrected_from_closure_id !== null;
    }

    /**
     * Permite anular o fecho no painel: só enquanto ninguém aceitou nem contestou.
     */
    public function canDeleteWhileAllPending(): bool
    {
        if (! $this->acknowledgements()->exists()) {
            return false;
        }

        return ! $this->acknowledgements()
            ->where('status', '!=', EmployeePayPeriodAcknowledgement::STATUS_PENDENTE)
            ->exists();
    }
}
