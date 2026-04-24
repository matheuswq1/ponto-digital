<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_id',
        'department_id',
        'cpf',
        'cargo',
        'department',
        'registration_number',
        'admission_date',
        'dismissal_date',
        'contract_type',
        'weekly_hours',
        'pis',
        'active',
        'photo_url',
        'face_enrolled',
    ];

    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'dismissal_date' => 'date',
            'active' => 'boolean',
            'face_enrolled' => 'boolean',
            'weekly_hours' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Departamento cadastrado (escala do menu Departamentos). */
    public function dept(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function timeRecords(): HasMany
    {
        return $this->hasMany(TimeRecord::class);
    }

    public function workDays(): HasMany
    {
        return $this->hasMany(WorkDay::class);
    }

    public function workSchedule(): HasOne
    {
        return $this->hasOne(WorkSchedule::class)->where('active', true)->latest();
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function hourBankTransactions(): HasMany
    {
        return $this->hasMany(HourBankTransaction::class);
    }

    public function hourBankRequests(): HasMany
    {
        return $this->hasMany(HourBankRequest::class);
    }

    /**
     * Saldo atual do banco de horas em minutos.
     * Positivo = crédito, negativo = débito.
     */
    public function getHourBankBalanceMinutesAttribute(): int
    {
        return (int) $this->hourBankTransactions()->sum('minutes');
    }

    /**
     * Saldo formatado como "HH:MM" com sinal (+/-).
     */
    public function getHourBankBalanceFormattedAttribute(): string
    {
        $minutes = $this->hour_bank_balance_minutes;
        $sign    = $minutes >= 0 ? '+' : '-';
        $abs     = abs($minutes);
        return sprintf('%s%02d:%02d', $sign, intdiv($abs, 60), $abs % 60);
    }

    public function getTodayRecordsAttribute()
    {
        return $this->timeRecords()
            ->whereDate('datetime', today())
            ->orderBy('datetime')
            ->get();
    }

    public function getLastRecordAttribute()
    {
        return $this->timeRecords()->latest('datetime')->first();
    }

    public function dailyExpectedMinutes(): int
    {
        return (int) round(($this->weekly_hours / 5) * 60);
    }
}
