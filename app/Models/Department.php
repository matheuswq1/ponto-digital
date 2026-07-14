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
        'entry_time_by_day',
        'exit_time_by_day',
        'lunch_minutes',
        'lunch_minutes_by_day',
        'tolerance_minutes',
        'tolerance_mode',
        'work_days',
        'active',
        'app_punch_disabled',
    ];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'active' => 'boolean',
            'app_punch_disabled' => 'boolean',
            'lunch_minutes' => 'integer',
            'tolerance_minutes' => 'integer',
            'lunch_minutes_by_day' => 'array',
            'entry_time_by_day' => 'array',
            'exit_time_by_day' => 'array',
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

    /** Tem gabarito de entrada/saída (padrão ou mapa por dia). */
    public function hasGabarito(): bool
    {
        return $this->getEntryTimeForDay(1) !== null && $this->getExitTimeForDay(1) !== null;
    }

    /**
     * Horário de entrada para o dia da semana (0=dom .. 6=sáb).
     */
    public function getEntryTimeForDay(int $dayOfWeek): ?string
    {
        return $this->timeFromMapOrDefault($this->entry_time_by_day, $dayOfWeek, $this->entry_time);
    }

    /**
     * Horário de saída para o dia da semana (0=dom .. 6=sáb).
     */
    public function getExitTimeForDay(int $dayOfWeek): ?string
    {
        return $this->timeFromMapOrDefault($this->exit_time_by_day, $dayOfWeek, $this->exit_time);
    }

    public function getExpectedMinutes(): int
    {
        if (! $this->hasGabarito()) {
            return 0;
        }
        $wd = $this->workDaysList();
        $mins = [];
        foreach ($wd as $d) {
            $mins[] = $this->getExpectedMinutesForDay((int) $d);
        }

        return $mins === [] ? 0 : max($mins);
    }

    /**
     * Minutos de intervalo de almoço para o dia da semana (0=dom .. 6=sáb).
     */
    public function getLunchMinutesForDay(int $dayOfWeek): int
    {
        $d = (int) $dayOfWeek;
        $map = $this->lunch_minutes_by_day;
        if (is_array($map)) {
            if (array_key_exists($d, $map)) {
                return max(0, (int) $map[$d]);
            }
            if (array_key_exists((string) $d, $map)) {
                return max(0, (int) $map[(string) $d]);
            }
        }

        return max(0, (int) ($this->lunch_minutes ?? 0));
    }

    public function getExpectedMinutesForDay(int $dayOfWeek): int
    {
        $entryTime = $this->getEntryTimeForDay($dayOfWeek);
        $exitTime = $this->getExitTimeForDay($dayOfWeek);
        if ($entryTime === null || $exitTime === null) {
            return 0;
        }
        $entry = strtotime($entryTime);
        $exit = strtotime($exitTime);
        if ($entry === false || $exit === false) {
            return 0;
        }
        $total = (int) (($exit - $entry) / 60);
        $lunch = $this->getLunchMinutesForDay($dayOfWeek);

        return max(0, $total - $lunch);
    }

    public function hasVariableLunchByDay(): bool
    {
        if (! is_array($this->lunch_minutes_by_day) || $this->lunch_minutes_by_day === []) {
            return false;
        }
        $vals = [];
        foreach (range(0, 6) as $d) {
            $vals[] = $this->getLunchMinutesForDay($d);
        }

        return count(array_unique($vals)) > 1;
    }

    public function hasVariableScheduleByDay(): bool
    {
        $hasEntryMap = is_array($this->entry_time_by_day) && $this->entry_time_by_day !== [];
        $hasExitMap = is_array($this->exit_time_by_day) && $this->exit_time_by_day !== [];
        if (! $hasEntryMap && ! $hasExitMap) {
            return false;
        }
        $pairs = [];
        foreach (range(0, 6) as $d) {
            $pairs[] = ($this->getEntryTimeForDay($d) ?? '').'|'.($this->getExitTimeForDay($d) ?? '');
        }

        return count(array_unique($pairs)) > 1;
    }

    /**
     * Parte a jornada em manhã / intervalo / tarde para o gabarito do cartão ponto.
     */
    public function getGabaritoTimes(): ?array
    {
        if (! $this->hasGabarito()) {
            return null;
        }
        $wd = $this->workDaysList()[0] ?? 1;

        return $this->getGabaritoTimesForDay($wd);
    }

    public function getGabaritoTimesForDay(int $dayOfWeek): ?array
    {
        $entryTime = $this->getEntryTimeForDay($dayOfWeek);
        $exitTime = $this->getExitTimeForDay($dayOfWeek);
        if ($entryTime === null || $exitTime === null) {
            return null;
        }
        $e = Carbon::parse('2000-01-01 '.$entryTime);
        $x = Carbon::parse('2000-01-01 '.$exitTime);
        if ($x->lessThanOrEqualTo($e)) {
            return null;
        }
        $lunch = $this->getLunchMinutesForDay($dayOfWeek);
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

    private function timeFromMapOrDefault(mixed $map, int $dayOfWeek, mixed $default): ?string
    {
        $d = (int) $dayOfWeek;
        if (is_array($map)) {
            $raw = $map[$d] ?? $map[(string) $d] ?? null;
            $formatted = $this->formatTimeValue($raw);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        return $this->formatTimeValue($default);
    }

    private function formatTimeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse('2000-01-01 '.(string) $value)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
