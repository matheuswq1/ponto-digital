<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\TimeRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TimeRecordWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $dateFrom    = $request->get('date_from', today()->toDateString());
        $dateTo      = $request->get('date_to', today()->toDateString());
        $search      = $request->get('q');
        $employeeId  = $request->get('employee_id');

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $from = Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()->utc();
        $to   = Carbon::createFromFormat('Y-m-d', $dateTo,   $tz)->endOfDay()->utc();

        $records = TimeRecord::with('employee.user')
            ->whereBetween('datetime', [$from, $to])
            ->when($search, fn($q) => $q->whereHas('employee.user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")
            ))
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->orderByDesc('datetime')
            ->paginate(30)
            ->withQueryString();

        $employees = Employee::with('user')->where('active', true)->orderBy('id')->get();

        return view('web.pontos.index', compact(
            'records', 'dateFrom', 'dateTo', 'search', 'employees', 'employeeId'
        ));
    }

    public function export(Request $request)
    {
        $this->authorize('manage-employees');

        $dateFrom   = $request->get('date_from', today()->startOfMonth()->toDateString());
        $dateTo     = $request->get('date_to', today()->toDateString());
        $employeeId = $request->get('employee_id');
        $search     = $request->get('q');

        $tz   = config('app.timezone', 'America/Sao_Paulo');
        $from = Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()->utc();
        $to   = Carbon::createFromFormat('Y-m-d', $dateTo,   $tz)->endOfDay()->utc();

        $records = TimeRecord::with('employee.user')
            ->whereBetween('datetime', [$from, $to])
            ->when($search, fn($q) => $q->whereHas('employee.user', fn($u) =>
                $u->where('name', 'like', "%{$search}%")
            ))
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->orderBy('datetime')
            ->get();

        $filename = 'pontos_' . $dateFrom . '_a_' . $dateTo;
        if ($employeeId) {
            $emp = Employee::with('user')->find($employeeId);
            if ($emp) {
                $slug   = Str::slug($emp->user?->name ?? 'colaborador-' . $emp->id, '_');
                $filename = 'pontos_' . $slug . '_' . $dateFrom . '_a_' . $dateTo;
            }
        }
        $filename .= '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'Colaborador', 'E-mail', 'Tipo', 'Data/Hora',
                'Latitude', 'Longitude', 'Offline', 'Foto',
            ], ';');

            foreach ($records as $rec) {
                fputcsv($handle, [
                    $rec->id,
                    $rec->employee->user->name ?? '',
                    $rec->employee->user->email ?? '',
                    $rec->getTypeLabel(),
                    $rec->datetime_local?->format('d/m/Y H:i:s') ?? '',
                    $rec->latitude ?? '',
                    $rec->longitude ?? '',
                    $rec->offline ? 'Offline' : 'Online',
                    $rec->photo_url ? 'Sim' : 'Não',
                ], ';');
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportCartaoCSV(array $cards, string $dateFrom, string $dateTo)
    {
        $tz       = config('app.timezone', 'America/Sao_Paulo');
        $diasSem  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        $filename = "cartao_ponto_{$dateFrom}_a_{$dateTo}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $fmtMin = fn(int $m): string => $m === 0 ? '' : sprintf('%02d:%02d', intdiv($m,60), $m%60);

        $callback = function () use ($cards, $diasSem, $fmtMin) {
            $h = fopen('php://output', 'w');
            fputs($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Colaborador','Departamento','Empresa','Data','Dia','ENT1','SAI1','ENT2','SAI2','ENT3','SAI3','Trabalhado','Faltas','EX50%','EX100%','EXF01','Extras','Feriado','Banco OK'], ';');
            foreach ($cards as $card) {
                $emp  = $card['employee'];
                $nome = $emp->user?->name ?? '—';
                $dept = $emp->dept?->name ?? '—';
                $emp_company = $emp->company?->name ?? '—';
                foreach ($card['days'] as $day) {
                    if ($day['folga'] && $day['worked_min'] === 0) continue;
                    $dw  = (int) $day['date']->format('w');
                    $bat = $day['batidas'];
                    fputcsv($h, [
                        $nome, $dept, $emp_company,
                        $day['date']->format('d/m/Y'),
                        $diasSem[$dw],
                        $bat[0]['ent'], $bat[0]['sai'],
                        $bat[1]['ent'], $bat[1]['sai'],
                        $bat[2]['ent'], $bat[2]['sai'],
                        $fmtMin($day['worked_min']),
                        $fmtMin($day['falta_min']),
                        $fmtMin($day['extra_50_min']),
                        $fmtMin($day['extra_100_min']),
                        $fmtMin($day['extra_noc_min']),
                        $fmtMin($day['extra_min']),
                        $day['is_holiday'] ? 'Sim' : '',
                        $day['banco_ok'] ? 'Sim' : 'Não',
                    ], ';');
                }
            }
            fclose($h);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function cartaoPonto(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('manage-employees');

        $tz         = config('app.timezone', 'America/Sao_Paulo');
        $dateFrom   = $request->get('date_from', today()->startOfMonth()->toDateString());
        $dateTo     = $request->get('date_to',   today()->endOfMonth()->toDateString());
        $employeeId = $request->get('employee_id');
        $searchQ    = $request->get('q');

        if (! $employeeId && $searchQ) {
            $matchIds = Employee::query()
                ->where('active', true)
                ->whereHas('user', fn($u) => $u->where('name', 'like', "%{$searchQ}%"))
                ->pluck('id');
            if ($matchIds->count() === 1) {
                $employeeId = (string) $matchIds->first();
            }
        }

        $from = Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()->utc();
        $to   = Carbon::createFromFormat('Y-m-d', $dateTo,   $tz)->endOfDay()->utc();

        $employeesQuery = Employee::with(['user', 'company', 'workSchedule', 'dept'])
            ->where('active', true)
            ->when($employeeId, fn($q) => $q->where('id', $employeeId))
            ->orderBy('id');

        $allEmployees = Employee::with('user')->where('active', true)->orderBy('id')->get();
        $employees    = $employeesQuery->get();

        // Para cada colaborador, montar os dias do período com as batidas
        $cards = [];
        foreach ($employees as $emp) {
            $records = TimeRecord::where('employee_id', $emp->id)
                ->whereBetween('datetime', [$from, $to])
                ->orderBy('datetime')
                ->get();

            // WorkDays processados (banco de horas) — índice por data
            $workDaysProcessed = $emp->workDays()
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->where('is_closed', true)
                ->get(['date', 'extra_minutes'])
                ->mapWithKeys(fn($wd) => [\Carbon\Carbon::parse($wd->date)->toDateString() => $wd->extra_minutes])
                ->toArray();

            // Agrupar batidas por dia (horário local)
            $byDay = [];
            foreach ($records as $rec) {
                $day = $rec->datetime->setTimezone($tz)->toDateString();
                $byDay[$day][] = $rec;
            }

            $ws   = $emp->workSchedule;
            $dept = $emp->dept;
            $deptRef = $dept && $dept->entry_time && $dept->exit_time ? $dept : null;
            $workDays = $deptRef
                ? $deptRef->workDaysList()
                : ($ws?->work_days ?? [1, 2, 3, 4, 5]);

            $days   = [];
            $totalWorkedMin   = 0;
            $totalExtraMin    = 0;
            $totalExtra50Min  = 0;
            $totalExtra100Min = 0;
            $totalExtraNocMin = 0;
            $totalFaltaMin    = 0;

            // Pré-calcular feriados do período para evitar N queries
            $companyId  = $emp->company_id;
            $holidaySet = array_flip(Holiday::datesInPeriod($dateFrom, $dateTo, $companyId));

            $period = CarbonPeriod::create($dateFrom, $dateTo);
            foreach ($period as $date) {
                $dateStr   = $date->toDateString();
                $dayOfWeek = (int) $date->format('w'); // 0=Dom, 6=Sab
                $isWorkDay = in_array($dayOfWeek, $workDays);
                $recs      = $byDay[$dateStr] ?? [];
                $isSunday  = ($dayOfWeek === 0);
                $isSaturday= ($dayOfWeek === 6);
                $isHoliday = isset($holidaySet[$dateStr]);

                // Separar entradas e saídas em ordem
                $entries = array_values(array_filter($recs, fn($r) => $r->type === 'entrada'));
                $exits   = array_values(array_filter($recs, fn($r) => $r->type === 'saida'));

                // Montar até 3 pares ENT/SAI
                $batidas = [];
                for ($i = 0; $i < 3; $i++) {
                    $batidas[] = [
                        'ent' => isset($entries[$i]) ? $entries[$i]->datetime->setTimezone($tz)->format('H:i') : '',
                        'sai' => isset($exits[$i])   ? $exits[$i]->datetime->setTimezone($tz)->format('H:i')   : '',
                    ];
                }

                // Calcular minutos trabalhados (soma dos pares ENT→SAI)
                $workedMin = 0;
                foreach ($entries as $idx => $ent) {
                    $sai = $exits[$idx] ?? null;
                    if ($sai) {
                        $workedMin += $ent->datetime->diffInMinutes($sai->datetime);
                    }
                }

                $expectedMin = $deptRef
                    ? $deptRef->getExpectedMinutesForDay($dayOfWeek)
                    : (($ws && $ws->entry_time && $ws->exit_time)
                        ? $ws->getExpectedMinutes()
                        : $emp->dailyExpectedMinutes());

                // Tolerância: usa departamento, depois escala individual, default 5 min
                $tolerance = (int) ($deptRef?->tolerance_minutes ?? $ws?->tolerance_minutes ?? 5);

                $extraMin     = 0;
                $extra50Min   = 0;  // sábado não-feriado
                $extra100Min  = 0;  // domingo ou feriado
                $extraNocMin  = 0;  // adicional noturno (EXF01) 22h–05h
                $faltaMin     = 0;

                if (count($recs) > 0) {
                    if ($isWorkDay && !$isHoliday) {
                        // Dia de trabalho normal: só conta extra/falta se ultrapassar tolerância
                        $diff = $workedMin - $expectedMin;
                        if ($diff > $tolerance) {
                            $extraMin = $diff;
                            if ($isSunday)       $extra100Min = $diff;
                            elseif ($isSaturday) $extra50Min  = $diff;
                            // seg-sex útil: vai só em extraMin
                        }
                        if ($diff < -$tolerance) $faltaMin = abs($diff);
                    } else {
                        // Folga, domingo, feriado ou sábado de folga trabalhado → tudo extra
                        if ($isSunday || $isHoliday) {
                            $extra100Min = $workedMin; $extraMin = $workedMin;
                        } elseif ($isSaturday) {
                            $extra50Min  = $workedMin; $extraMin = $workedMin;
                        } else {
                            $extraMin = $workedMin;
                        }
                    }

                    // Adicional noturno (EXF01): minutos entre 22:00–05:00
                    foreach ($entries as $idx => $ent) {
                        $sai = $exits[$idx] ?? null;
                        if (! $sai) continue;
                        $start = $ent->datetime->setTimezone($tz);
                        $end   = $sai->datetime->setTimezone($tz);
                        $cur   = $start->copy();
                        while ($cur->lt($end)) {
                            $h = (int) $cur->format('H');
                            if ($h >= 22 || $h < 5) $extraNocMin++;
                            $cur->addMinute();
                        }
                    }

                    $totalWorkedMin   += $workedMin;
                    $totalExtraMin    += $extraMin;
                    $totalExtra50Min  += $extra50Min;
                    $totalExtra100Min += $extra100Min;
                    $totalExtraNocMin += $extraNocMin;
                    $totalFaltaMin    += $faltaMin;
                }

                $days[] = [
                    'date'          => $date->copy(),
                    'date_str'      => $dateStr,
                    'is_work_day'   => $isWorkDay,
                    'is_holiday'    => $isHoliday,
                    'batidas'       => $batidas,
                    'worked_min'    => $workedMin,
                    'extra_min'     => $extraMin,
                    'extra_50_min'  => $extra50Min,
                    'extra_100_min' => $extra100Min,
                    'falta_min'     => $faltaMin,
                    'folga'         => !$isWorkDay && !$isHoliday && count($recs) === 0,
                    'sem_ponto'     => ($isWorkDay && !$isHoliday) && count($recs) === 0,
                    'extra_noc_min' => $extraNocMin,
                    'banco_ok'      => array_key_exists($dateStr, $workDaysProcessed),
                    'banco_min'     => $workDaysProcessed[$dateStr] ?? null,
                ];
            }

            $cards[] = [
                'employee'          => $emp,
                'days'              => $days,
                'total_worked'      => $totalWorkedMin,
                'total_extra'       => $totalExtraMin,
                'total_extra_50'    => $totalExtra50Min,
                'total_extra_100'   => $totalExtra100Min,
                'total_extra_noc'   => $totalExtraNocMin,
                'total_falta'       => $totalFaltaMin,
                'date_from'         => $dateFrom,
                'date_to'           => $dateTo,
            ];
        }

        // Exportação CSV do cartão
        if ($request->get('export') === 'csv') {
            return $this->exportCartaoCSV($cards, $dateFrom, $dateTo);
        }

        return view('web.pontos.cartao', compact(
            'cards', 'allEmployees', 'employeeId', 'dateFrom', 'dateTo'
        ));
    }
}
