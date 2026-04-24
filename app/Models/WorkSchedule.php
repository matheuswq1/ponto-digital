<?php

namespace App\Models;

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
        'work_days',
        'active',
        'notify_late',
        'notify_absence',
        'notify_overtime',
    ];

    protected function casts(): array
    {
        return [
            'work_days'         => 'array',
            'active'            => 'boolean',
            'tolerance_minutes' => 'integer',
            'lunch_minutes'     => 'integer',
            'notify_late'       => 'boolean',
            'notify_absence'    => 'boolean',
            'notify_overtime'   => 'boolean',
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
        $exit  = strtotime($this->exit_time);
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
}
