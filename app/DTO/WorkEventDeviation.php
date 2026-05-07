<?php

declare(strict_types=1);

namespace App\DTO;

use Carbon\Carbon;

/**
 * Um par previsto × real para o motor CLT por evento.
 */
final readonly class WorkEventDeviation
{
    public function __construct(
        public string $type,
        public ?Carbon $expectedAt,
        public Carbon $actualAt,
        public int $diffMinutes,
        public bool $withinEventTolerance,
        public bool $enteredCltBucket,
        public bool $outsideEventTolerance,
    ) {}

    /** @return array<string, mixed> */
    public function toSnapshotEventArray(): array
    {
        return [
            'type' => $this->type,
            'expected' => $this->expectedAt?->format('H:i'),
            'actual' => $this->actualAt->format('H:i'),
            'diff' => $this->diffMinutes,
            'within_event_tolerance' => $this->withinEventTolerance,
            'entered_clt_bucket' => $this->enteredCltBucket,
            'outside_event_tolerance' => $this->outsideEventTolerance,
            'classification' => $this->outsideEventTolerance
                ? 'outside_event_tolerance'
                : 'within_event_tolerance',
        ];
    }
}
