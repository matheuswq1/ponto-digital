<?php

namespace App\Http\Resources;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class WorkDayResource extends JsonResource
{
    /**
     * Calendário e rotulos para o app (sábado/dom, feriado na base, falta/folga/etc.).
     *
     * @return array{
     *     weekday: int|null,
     *     is_saturday: bool,
     *     is_sunday: bool,
     *     is_holiday: bool,
     *     labels_pt: list<string>
     * }
     */
    protected function dayCalendarForApi(): array
    {
        $rawDate = $this->date;
        if ($rawDate === null) {
            return [
                'weekday' => null,
                'is_saturday' => false,
                'is_sunday' => false,
                'is_holiday' => false,
                'labels_pt' => [],
            ];
        }

        $dateStr = $rawDate instanceof Carbon ? $rawDate->toDateString() : (string) $rawDate;
        $tz = config('app.timezone', 'America/Sao_Paulo');
        $calendarCarbon = Carbon::parse($dateStr.' 12:00:00', $tz);

        $companyId = $this->relationLoaded('employee')
            ? $this->employee?->company_id
            : null;

        $w = (int) $calendarCarbon->format('w');
        $isSaturday = $w === 6;
        $isSunday = $w === 0;
        $isHolidayCal = Schema::hasTable('holidays')
            && Holiday::isHoliday($calendarCarbon, $companyId);

        $labels = [];
        $status = $this->status ?? 'normal';

        switch ($status) {
            case 'falta':
                $labels[] = 'Falta';
                break;
            case 'afastamento':
                $labels[] = 'Afastamento';
                break;
            case 'folga':
                $labels[] = 'Folga';
                break;
            case 'feriado':
                $labels[] = 'Feriado';
                break;
            default:
                break;
        }

        if ($isHolidayCal && $status !== 'feriado') {
            $labels[] = 'Feriado';
        }

        if ($isSunday) {
            $labels[] = 'Domingo';
        } elseif ($isSaturday) {
            $labels[] = 'Sábado';
        }

        return [
            'weekday' => $w,
            'is_saturday' => $isSaturday,
            'is_sunday' => $isSunday,
            'is_holiday' => $isHolidayCal,
            'labels_pt' => array_values(array_unique($labels)),
        ];
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->toDateString(),
            'date_formatted' => $this->date?->format('d/m/Y'),
            'week_day' => $this->date?->locale('pt_BR')->isoFormat('dddd'),
            'times' => [
                'entry' => $this->entry_time,
                'lunch_start' => $this->lunch_start,
                'lunch_end' => $this->lunch_end,
                'exit' => $this->exit_time,
            ],
            'minutes' => [
                'total' => $this->total_minutes,
                'expected' => $this->expected_minutes,
                'extra' => $this->extra_minutes,
                'lunch' => $this->lunch_minutes,
            ],
            'hours' => [
                'total' => $this->formatted_total,
                'extra' => $this->formatted_extra,
            ],
            'status' => $this->status,
            'observations' => $this->observations,
            'is_closed' => $this->is_closed,
            'balance_type' => $this->extra_minutes > 0 ? 'positivo' : ($this->extra_minutes < 0 ? 'negativo' : 'neutro'),
            'day_calendar' => $this->dayCalendarForApi(),
            'tolerance_meta' => $this->toleranceMetaForApi(),
        ];
    }
}
