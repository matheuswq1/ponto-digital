<?php

namespace App\DTO;

use App\Models\Department;
use App\Models\WorkSchedule;
use App\Services\WorkToleranceResolver;

/**
 * Contexto único de tolerância / entrada / dias úteis — sempre da mesma cascata de resolução.
 */
readonly class WorkToleranceContext
{
    public const SOURCE_DEPARTMENT = 'department';

    public const SOURCE_WORK_SCHEDULE = 'work_schedule';

    public const SOURCE_COMPANY = 'company';

    public const SOURCE_DEFAULT = 'default';

    /**
     * @param  array<int, int>  $workDays  0=Dom … 6=Sáb
     * @param  string  $calendarDate  Y-m-d no fuso efetivo da empresa
     */
    public function __construct(
        public int $toleranceMinutes,
        public string $toleranceMode,
        public ?string $entryTime,
        public array $workDays,
        public ?Department $departmentTemplate,
        public ?WorkSchedule $workSchedule,
        public string $modeResolvedFrom,
        public string $timezone,
        public string $calendarDate,
    ) {}

    public function appliedModeDescriptionPt(): string
    {
        $modeLabel = match ($this->toleranceMode) {
            WorkToleranceResolver::MODE_DAILY_DISCOUNT => 'Desconto no saldo diário',
            default => 'Faixa neutra (dead band)',
        };

        $sourceLabel = match ($this->modeResolvedFrom) {
            self::SOURCE_DEPARTMENT => 'departamento (gabarito)',
            self::SOURCE_WORK_SCHEDULE => 'escala individual',
            self::SOURCE_COMPANY => 'empresa',
            default => 'padrão do sistema',
        };

        return "{$modeLabel} · definido por: {$sourceLabel}";
    }

    public function timezoneHintPt(): string
    {
        return 'Fuso para dia útil e alertas: '.$this->timezone;
    }
}
