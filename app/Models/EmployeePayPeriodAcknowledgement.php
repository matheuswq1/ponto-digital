<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeePayPeriodAcknowledgement extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_REJEITADO = 'rejeitado';

    protected $table = 'pay_period_acknowledgements';

    protected $fillable = [
        'pay_period_closure_id',
        'employee_id',
        'status',
        'employee_notes',
        'responded_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    public function payPeriodClosure(): BelongsTo
    {
        return $this->belongsTo(PayPeriodClosure::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(PayPeriodAckAuditEvent::class, 'pay_period_acknowledgement_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }
}
