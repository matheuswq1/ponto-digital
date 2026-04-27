<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudAttempt extends Model
{
    protected $fillable = [
        'employee_id',
        'company_id',
        'rule_triggered',
        'details',
        'latitude',
        'longitude',
        'device_id',
        'ip_address',
        'action_taken',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'details'      => 'array',
            'latitude'     => 'decimal:7',
            'longitude'    => 'decimal:7',
            'notified_at'  => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getRuleLabel(): string
    {
        return match ($this->rule_triggered) {
            'mock_location'    => 'GPS Falso',
            'velocity_jump'    => 'Salto de Localização',
            'wifi_mismatch'    => 'Wi-Fi não autorizado',
            'ip_city_mismatch' => 'Cidade do IP divergente',
            default            => $this->rule_triggered,
        };
    }

    public function getActionLabel(): string
    {
        return match ($this->action_taken) {
            'blocked' => 'Bloqueado',
            'warned'  => 'Avisado',
            'logged'  => 'Registado',
            default   => $this->action_taken,
        };
    }
}
