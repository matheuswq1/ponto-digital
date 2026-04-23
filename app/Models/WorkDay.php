<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'entry_time',
        'lunch_start',
        'lunch_end',
        'exit_time',
        'total_minutes',
        'expected_minutes',
        'extra_minutes',
        'lunch_minutes',
        'status',
        'observations',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_minutes' => 'integer',
            'expected_minutes' => 'integer',
            'extra_minutes' => 'integer',
            'lunch_minutes' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getTotalHoursAttribute(): float
    {
        return round($this->total_minutes / 60, 2);
    }

    public function getExtraHoursAttribute(): float
    {
        return round($this->extra_minutes / 60, 2);
    }

    public function isPositiveBalance(): bool
    {
        return $this->extra_minutes > 0;
    }

    public function isNegativeBalance(): bool
    {
        return $this->extra_minutes < 0;
    }

    public function getFormattedTotalAttribute(): string
    {
        $hours = intdiv(abs($this->total_minutes), 60);
        $minutes = abs($this->total_minutes) % 60;
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function getFormattedExtraAttribute(): string
    {
        $sign = $this->extra_minutes < 0 ? '-' : '+';
        $hours = intdiv(abs($this->extra_minutes), 60);
        $minutes = abs($this->extra_minutes) % 60;
        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }
}
