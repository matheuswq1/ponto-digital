<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'name',
        'entry_time',
        'exit_time',
        'lunch_minutes',
        'tolerance_minutes',
        'tolerance_mode',
        'work_days',
        'active',
        'notify_late',
        'notify_absence',
        'notify_overtime',
    ];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'active' => 'boolean',
            'tolerance_minutes' => 'integer',
            'lunch_minutes' => 'integer',
            'notify_late' => 'boolean',
            'notify_absence' => 'boolean',
            'notify_overtime' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getExpectedMinutes(): int
    {
        if (empty($this->entry_time) || empty($this->exit_time)) {
            return 0;
        }
        $entry = strtotime($this->entry_time);
        $exit = strtotime($this->exit_time);
        if ($entry === false || $exit === false) {
            return 0;
        }
        $total = (int) (($exit - $entry) / 60);

        // Deduz apenas o intervalo mínimo configurado (opcional)
        $lunch = $this->lunch_minutes ?? 0;

        return max(0, $total - $lunch);
    }

    public function isWorkDay(int $dayOfWeek): bool
    {
        return in_array($dayOfWeek, $this->work_days ?? [1, 2, 3, 4, 5]);
    }

    public function workDaysList(): array
    {
        $d = $this->work_days;

        return is_array($d) && $d !== [] ? array_map('intval', $d) : [1, 2, 3, 4, 5];
    }

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
        $lunch = (int) ($this->lunch_minutes ?? 0);
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
}
