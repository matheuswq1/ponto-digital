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

    /** Modo CLT por batida (5+10) — gabarito fixo nos 4 eventos. */
    public const MODE_CLT_EVENT_BASED = 'clt_event_based';

    /** CLT por batida com retorno do almoço = saída real do almoço + duração configurada. */
    public const MODE_CLT_EVENT_STRICT = 'clt_event_strict';

    /** @return list<string> */
    public static function modes(): array
    {
        return [
            self::MODE_DAILY_DEAD_BAND,
            self::MODE_DAILY_DISCOUNT,
            self::MODE_CLT_EVENT_BASED,
            self::MODE_CLT_EVENT_STRICT,
        ];
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
        return ($dept && $dept->entry_time && $dept->exit_time) ? $dept : null;
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
        $calendarDateStr = $calendarDate->copy()->setTimezone($tz)->format('Y-m-d');

        $toleranceMinutes = (int) ($deptRef?->tolerance_minutes ?? $schedule?->tolerance_minutes ?? 5);

        [$toleranceMode, $modeResolvedFrom] = $this->resolveModeAndSource($deptRef, $schedule, $company);

        $entryTime = $deptRef
            ? ($deptRef->entry_time !== null ? (string) $deptRef->entry_time : null)
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

        if ($mode === self::MODE_CLT_EVENT_BASED || $mode === self::MODE_CLT_EVENT_STRICT) {
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
