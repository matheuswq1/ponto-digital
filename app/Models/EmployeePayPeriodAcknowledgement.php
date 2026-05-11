<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function payPeriodClosure(): BelongsTo
    {
        return $this->belongsTo(PayPeriodClosure::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }
}
