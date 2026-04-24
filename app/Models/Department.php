<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'entry_time',
        'exit_time',
        'lunch_minutes',
        'tolerance_minutes',
        'work_days',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'work_days'         => 'array',
            'active'            => 'boolean',
            'lunch_minutes'     => 'integer',
            'tolerance_minutes' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function getExpectedMinutes(): int
    {
        if (empty($this->entry_time) || empty($this->exit_time)) {
            return 0;
        }
        $entry = strtotime($this->entry_time);
        $exit  = strtotime($this->exit_time);
        if ($entry === false || $exit === false) {
            return 0;
        }
        $total = (int) (($exit - $entry) / 60);
        $lunch = $this->lunch_minutes ?? 0;

        return max(0, $total - $lunch);
    }

    /**
     * Parte a jornada em manhã / intervalo / tarde para o gabarito do cartão ponto.
     */
    public function getGabaritoTimes(): ?array
    {
        if (empty($this->entry_time) || empty($this->exit_time)) {
            return null;
        }
        $e = Carbon::parse('2000-01-01 '.$this->entry_time);
        $x = Carbon::parse('2000-01-01 '.$this->exit_time);
        if ($x->lessThanOrEqualTo($e)) {
            return null;
        }
        $lunch   = (int) ($this->lunch_minutes ?? 0);
        $workMin = (int) $e->diffInMinutes($x) - $lunch;
        if ($workMin < 0) {
            return null;
        }
        $h1 = (int) floor($workMin / 2);
        $s1 = $e->copy()->addMinutes($h1);
        $e2 = $s1->copy()->addMinutes($lunch);

        return [
            'e1' => $e->format('H:i'),
            's1' => $s1->format('H:i'),
            'e2' => $e2->format('H:i'),
            's2' => $x->format('H:i'),
        ];
    }

    public function workDaysList(): array
    {
        $d = $this->work_days;

        return is_array($d) && $d !== [] ? array_map('intval', $d) : [1, 2, 3, 4, 5];
    }
}
