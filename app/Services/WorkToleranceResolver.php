<?php

namespace App\Services;

use App\DTO\WorkToleranceContext;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Carbon\Carbon;

class WorkToleranceResolver
{
    public const MODE_DAILY_DEAD_BAND = 'daily_dead_band';

    public const MODE_DAILY_DISCOUNT = 'daily_discount';

    /**
     * Único modo CLT: bucket progressivo (5+10) + intervalo de almoço por **duração real vs configurada**
     * (efeito jornada: delta = configurado − real; horário de saída para almoço não é comparado ao gabarito).
     */
    public const MODE_CLT_EVENT = 'clt_event_progressive_duration';

    /** @deprecated Use {@see self::MODE_CLT_EVENT} — mesmo slug, mantido para chamadas antigas. */
    public const MODE_CLT_EVENT_PROGRESSIVE_DURATION = self::MODE_CLT_EVENT;

    /** @var array<string, string> */
    private const LEGACY_CLT_MODE_MAP = [
        'clt_event_based' => self::MODE_CLT_EVENT,
        'clt_event_strict' => self::MODE_CLT_EVENT,
        'clt_event_progressive_cap' => self::MODE_CLT_EVENT,
    ];

    /** @return list<string> */
    public static function modes(): array
    {
        return [
            self::MODE_DAILY_DEAD_BAND,
            self::MODE_DAILY_DISCOUNT,
            self::MODE_CLT_EVENT,
        ];
    }

    public static function normalizeToleranceMode(string $mode): string
    {
        return self::LEGACY_CLT_MODE_MAP[$mode] ?? $mode;
    }

    /** Fuso efetivo para jornada / alertas (IANA). */
    public static function effectiveTimezone(?Company $company): string
    {
        $tz = $company?->timezone;
        if (is_string($tz) && $tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return config('app.timezone', 'America/Sao_Paulo');
    }

    public function resolveDepartmentReference(?Department $dept): ?Department
    {
        return ($dept && $dept->hasGabarito()) ? $dept : null;
    }

    /**
     * @param  Carbon  $calendarDate  instante no tempo usado só para derivar Y-m-d no fuso da empresa
     */
    public function resolve(Employee $employee, Carbon $calendarDate): WorkToleranceContext
    {
        $employee->loadMissing(['workSchedule', 'dept', 'company']);

        $deptRef = $this->resolveDepartmentReference($employee->dept);
        $schedule = $employee->workSchedule;
        $company = $employee->company;

        $tz = self::effectiveTimezone($company);
        $calendarDateLocal = $calendarDate->copy()->setTimezone($tz);
        $calendarDateStr = $calendarDateLocal->format('Y-m-d');
        $dayOfWeek = (int) $calendarDateLocal->format('w');

        $toleranceMinutes = (int) ($deptRef?->tolerance_minutes ?? $schedule?->tolerance_minutes ?? 5);

        [$toleranceMode, $modeResolvedFrom] = $this->resolveModeAndSource($deptRef, $schedule, $company);

        $entryTime = $deptRef
            ? $deptRef->getEntryTimeForDay($dayOfWeek)
            : ($schedule?->entry_time !== null ? (string) $schedule->entry_time : null);

        $workDays = $deptRef
            ? $deptRef->workDaysList()
            : ($schedule?->workDaysList() ?? [1, 2, 3, 4, 5]);

        return new WorkToleranceContext(
            toleranceMinutes: $toleranceMinutes,
            toleranceMode: $toleranceMode,
            entryTime: $entryTime,
            workDays: $workDays,
            departmentTemplate: $deptRef,
            workSchedule: $schedule,
            modeResolvedFrom: $modeResolvedFrom,
            timezone: $tz,
            calendarDate: $calendarDateStr,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveModeAndSource(?Department $deptRef, ?WorkSchedule $schedule, ?Company $company): array
    {
        $mode = self::MODE_DAILY_DEAD_BAND;
        $from = WorkToleranceContext::SOURCE_DEFAULT;

        if ($deptRef !== null && $this->filledMode($deptRef->tolerance_mode ?? null)) {
            $mode = (string) $deptRef->tolerance_mode;
            $from = WorkToleranceContext::SOURCE_DEPARTMENT;
        } elseif ($schedule !== null && $this->filledMode($schedule->tolerance_mode ?? null)) {
            $mode = (string) $schedule->tolerance_mode;
            $from = WorkToleranceContext::SOURCE_WORK_SCHEDULE;
        } elseif ($company !== null) {
            $mode = (string) ($company->tolerance_mode ?? self::MODE_DAILY_DEAD_BAND);
            $from = WorkToleranceContext::SOURCE_COMPANY;
        }

        $mode = self::normalizeToleranceMode($mode);

        if (! in_array($mode, self::modes(), true)) {
            $mode = self::MODE_DAILY_DEAD_BAND;
        }

        return [$mode, $from];
    }

    private function filledMode(?string $value): bool
    {
        return $value !== null && $value !== '';
    }

    public function applyToleranceToDiff(int|float $diff, int|float $tolerance, string $mode): int
    {
        $diff = (int) round((float) $diff);
        $tolerance = (int) round((float) $tolerance);

        if ($diff === 0) {
            return 0;
        }

        $mode = self::normalizeToleranceMode($mode);

        if ($mode === self::MODE_CLT_EVENT) {
            $mode = self::MODE_DAILY_DEAD_BAND;
        }

        if (! in_array($mode, [self::MODE_DAILY_DEAD_BAND, self::MODE_DAILY_DISCOUNT], true)) {
            $mode = self::MODE_DAILY_DEAD_BAND;
        }

        if ($mode === self::MODE_DAILY_DISCOUNT) {
            if (abs($diff) <= $tolerance) {
                return 0;
            }
            if ($diff > 0) {
                return max(0, $diff - $tolerance);
            }

            return min(0, $diff + $tolerance);
        }

        if ($diff > $tolerance) {
            return $diff;
        }
        if ($diff < -$tolerance) {
            return $diff;
        }

        return 0;
    }
}
