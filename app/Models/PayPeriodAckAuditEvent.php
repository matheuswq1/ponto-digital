<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayPeriodAckAuditEvent extends Model
{
    protected $table = 'pay_period_ack_audit_events';

    protected $fillable = [
        'pay_period_acknowledgement_id',
        'decision',
        'snapshot_hash',
        'snapshot_json',
        'ip_address',
        'user_agent',
        'client_meta',
        'terms_version',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'client_meta' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function acknowledgement(): BelongsTo
    {
        return $this->belongsTo(EmployeePayPeriodAcknowledgement::class, 'pay_period_acknowledgement_id');
    }
}
