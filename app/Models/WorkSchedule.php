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
        'lunch_start',
        'lunch_end',
        'exit_time',
        'tolerance_minutes',
        'work_days',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'active' => 'boolean',
            'tolerance_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getExpectedMinutes(): int
    {
        $entry = strtotime($this->entry_time);
        $exit = strtotime($this->exit_time);
        $lunch = (strtotime($this->lunch_end) - strtotime($this->lunch_start)) / 60;

        return (int) (($exit - $entry) / 60 - $lunch);
    }

    public function isWorkDay(int $dayOfWeek): bool
    {
        return in_array($dayOfWeek, $this->work_days ?? [1, 2, 3, 4, 5]);
    }
}
