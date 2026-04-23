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
