<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\TimeRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportWebController extends Controller
{
    // ────────────────────────────────────────────────────────────────────────────
    // Folha de pagamento consolidada
    // ────────────────────────────────────────────────────────────────────────────

    public function folhaPagamento(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('manage-employees');

        $tz        = config('app.timezone', 'America/Sao_Paulo');
        $dateFrom  = $request->get('date_from', today()->startOfMonth()->toDateString());
        $dateTo    = $request->get('date_to',   today()->endOfMonth()->toDateString());
        $companyId = $request->get('company_id');
        $deptId    = $request->get('dept_id');

        $from = Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()->utc();
        $to   = Carbon::createFromFormat('Y-m-d', $dateTo,   $tz)->endOfDay()->utc();

        $employeesQuery = Employee::with(['user', 'company', 'workSchedule', 'dept'])
            ->where('active', true)
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when($deptId,    fn($q) => $q->where('department_id', $deptId))
            ->orderBy('id');

        $companies   = Company::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $employees   = $employeesQuery->get();

        $rows = [];
        foreach ($employees as $emp) {
            $records = TimeRecord::where('employee_id', $emp->id)
                ->whereBetween('datetime', [$from, $to])
                ->orderBy('datetime')
                ->get();

            $ws   = $emp->workSchedule;
            $dept = $emp->dept;
            $deptRef = $dept && $dept->entry_time && $dept->exit_time ? $dept : null;
            $workDaysList = $deptRef
                ? $deptRef->workDaysList()
                : ($ws?->work_days ?? [1, 2, 3, 4, 5]);

            $companyId2  = $emp->company_id;
            $holidaySet  = array_flip(Holiday::datesInPeriod($dateFrom, $dateTo, $companyId2));

            // Agrupar batidas por dia
            $byDay = [];
            foreach ($records as $rec) {
                $day = $rec->datetime->setTimezone($tz)->toDateString();
                $byDay[$day][] = $rec;
            }

            $workedMin   = 0;
            $extraMin    = 0;
            $extra50Min  = 0;
            $extra100Min = 0;
            $extraNocMin = 0;
            $faltaMin    = 0;
            $diasTrabalhados = 0;
            $diasFalta       = 0;

            $period = CarbonPeriod::create($dateFrom, $dateTo);
            foreach ($period as $date) {
                $dateStr   = $date->toDateString();
                $dayOfWeek = (int) $date->format('w');
                $isWorkDay = in_array($dayOfWeek, $workDaysList);
                $isHoliday = isset($holidaySet[$dateStr]);
                $isSunday  = ($dayOfWeek === 0);
                $isSaturday= ($dayOfWeek === 6);
                $recs      = $byDay[$dateStr] ?? [];

                if (empty($recs)) {
                    if ($isWorkDay && !$isHoliday) $diasFalta++;
                    continue;
                }

                $entries = array_values(array_filter($recs, fn($r) => $r->type === 'entrada'));
                $exits   = array_values(array_filter($recs, fn($r) => $r->type === 'saida'));

                $dayWorked = 0;
                foreach ($entries as $idx => $ent) {
                    $sai = $exits[$idx] ?? null;
                    if ($sai) $dayWorked += $ent->datetime->diffInMinutes($sai->datetime);
                }

                $workedMin += $dayWorked;
                if ($dayWorked > 0) $diasTrabalhados++;

                $expectedMin = $deptRef
                    ? $deptRef->getExpectedMinutesForDay($dayOfWeek)
                    : (($ws && $ws->entry_time && $ws->exit_time) ? $ws->getExpectedMinutes() : $emp->dailyExpectedMinutes());

                $tolerance = (int)($deptRef?->tolerance_minutes ?? $ws?->tolerance_minutes ?? 5);

                if ($isWorkDay && !$isHoliday) {
                    $diff = $dayWorked - $expectedMin;
                    if ($diff > $tolerance) {
                        $extraMin += $diff;
                        if ($isSunday)       $extra100Min += $diff;
                        elseif ($isSaturday) $extra50Min  += $diff;
                    }
                    if ($diff < -$tolerance) $faltaMin += abs($diff);
                } else {
                    if ($isSunday || $isHoliday) {
                        $extra100Min += $dayWorked; $extraMin += $dayWorked;
                    } elseif ($isSaturday) {
                        $extra50Min  += $dayWorked; $extraMin += $dayWorked;
                    } else {
                        $extraMin += $dayWorked;
                    }
                }

                // Adicional noturno (22h–05h)
                foreach ($entries as $idx => $ent) {
                    $sai = $exits[$idx] ?? null;
                    if (!$sai) continue;
                    $cur = $ent->datetime->setTimezone($tz)->copy();
                    $end = $sai->datetime->setTimezone($tz);
                    while ($cur->lt($end)) {
                        $h = (int) $cur->format('H');
                        if ($h >= 22 || $h < 5) $extraNocMin++;
                        $cur->addMinute();
                    }
                }
            }

            $rows[] = [
                'id'              => $emp->id,
                'nome'            => $emp->user?->name ?? '—',
                'matricula'       => $emp->registration_number ?? '—',
                'cpf'             => $emp->cpf ?? '—',
                'pis'             => $emp->pis ?? '—',
                'cargo'           => $emp->cargo ?? '—',
                'departamento'    => $dept?->name ?? $emp->department ?? '—',
                'empresa'         => $emp->company?->name ?? '—',
                'admissao'        => $emp->admission_date?->format('d/m/Y') ?? '—',
                'horas_semana'    => $emp->weekly_hours ?? 44,
                'dias_trabalhados'=> $diasTrabalhados,
                'dias_falta'      => $diasFalta,
                'trabalhado_min'  => $workedMin,
                'extra_min'       => $extraMin,
                'extra_50_min'    => $extra50Min,
                'extra_100_min'   => $extra100Min,
                'extra_noc_min'   => $extraNocMin,
                'falta_min'       => $faltaMin,
            ];
        }

        if ($request->get('export') === 'csv') {
            return $this->exportFolhaCSV($rows, $dateFrom, $dateTo);
        }

        return view('web.reports.folha_pagamento', compact(
            'rows', 'dateFrom', 'dateTo',
            'companies', 'departments', 'companyId', 'deptId'
        ));
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Relatório de presença / ausência por período
    // ────────────────────────────────────────────────────────────────────────────

    public function presenca(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('manage-employees');

        $tz        = config('app.timezone', 'America/Sao_Paulo');
        $dateFrom  = $request->get('date_from', today()->startOfMonth()->toDateString());
        $dateTo    = $request->get('date_to',   today()->toDateString());
        $companyId = $request->get('company_id');
        $deptId    = $request->get('dept_id');

        $from = Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()->utc();
        $to   = Carbon::createFromFormat('Y-m-d', $dateTo,   $tz)->endOfDay()->utc();

        $companies   = Company::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        $employees = Employee::with(['user', 'company', 'workSchedule', 'dept'])
            ->where('active', true)
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when($deptId,    fn($q) => $q->where('department_id', $deptId))
            ->orderBy('id')
            ->get();

        $period = CarbonPeriod::create($dateFrom, $dateTo);
        $dates  = collect($period)->map(fn($d) => $d->toDateString())->values()->all();

        // Para cada colaborador, marcar cada dia como: P (presente), F (falta), H (feriado), Fo (folga)
        $rows = [];
        foreach ($employees as $emp) {
            $ws      = $emp->workSchedule;
            $dept    = $emp->dept;
            $deptRef = $dept && $dept->entry_time && $dept->exit_time ? $dept : null;
            $workDaysList = $deptRef
                ? $deptRef->workDaysList()
                : ($ws?->work_days ?? [1, 2, 3, 4, 5]);

            $holidaySet = array_flip(Holiday::datesInPeriod($dateFrom, $dateTo, $emp->company_id));

            // Dias com batidas
            $daysWithRecord = TimeRecord::where('employee_id', $emp->id)
                ->whereBetween('datetime', [$from, $to])
                ->selectRaw("DATE(CONVERT_TZ(datetime, '+00:00', '-03:00')) as d")
                ->groupByRaw("DATE(CONVERT_TZ(datetime, '+00:00', '-03:00'))")
                ->pluck('d')
                ->flip()
                ->all();

            $dias    = [];
            $totP    = 0; $totF = 0; $totH = 0; $totFo = 0;

            foreach ($dates as $dateStr) {
                $dayOfWeek = (int) Carbon::parse($dateStr)->format('w');
                $isWorkDay = in_array($dayOfWeek, $workDaysList);
                $isHoliday = isset($holidaySet[$dateStr]);
                $hasRecord = isset($daysWithRecord[$dateStr]);

                if ($isHoliday) {
                    $status = 'H'; $totH++;
                } elseif (!$isWorkDay) {
                    $status = 'Fo'; $totFo++;
                } elseif ($hasRecord) {
                    $status = 'P'; $totP++;
                } else {
                    $status = 'F'; $totF++;
                }
                $dias[$dateStr] = $status;
            }

            $rows[] = [
                'id'         => $emp->id,
                'nome'       => $emp->user?->name ?? '—',
                'empresa'    => $emp->company?->name ?? '—',
                'depto'      => $dept?->name ?? $emp->department ?? '—',
                'dias'       => $dias,
                'total_p'    => $totP,
                'total_f'    => $totF,
                'total_h'    => $totH,
                'total_fo'   => $totFo,
            ];
        }

        if ($request->get('export') === 'csv') {
            return $this->exportPresencaCSV($rows, $dates, $dateFrom, $dateTo);
        }

        return view('web.reports.presenca', compact(
            'rows', 'dates', 'dateFrom', 'dateTo',
            'companies', 'departments', 'companyId', 'deptId'
        ));
    }

    private function exportPresencaCSV(array $rows, array $dates, string $dateFrom, string $dateTo)
    {
        $filename = "presenca_{$dateFrom}_a_{$dateTo}.csv";
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows, $dates) {
            $h = fopen('php://output', 'w');
            fputs($h, "\xEF\xBB\xBF");
            $header = ['Colaborador', 'Empresa', 'Depto.'];
            foreach ($dates as $d) {
                $header[] = Carbon::parse($d)->format('d/m');
            }
            $header = array_merge($header, ['Presenças', 'Faltas', 'Feriados', 'Folgas']);
            fputcsv($h, $header, ';');

            foreach ($rows as $row) {
                $line = [$row['nome'], $row['empresa'], $row['depto']];
                foreach ($dates as $d) {
                    $line[] = $row['dias'][$d] ?? '';
                }
                $line[] = $row['total_p'];
                $line[] = $row['total_f'];
                $line[] = $row['total_h'];
                $line[] = $row['total_fo'];
                fputcsv($h, $line, ';');
            }
            fclose($h);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // CSV consolidado da folha
    // ────────────────────────────────────────────────────────────────────────────

    private function exportFolhaCSV(array $rows, string $dateFrom, string $dateTo)
    {
        $filename = "folha_pagamento_{$dateFrom}_a_{$dateTo}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $fmtMin = fn(int $m): string => $m === 0 ? '00:00' : sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        $callback = function () use ($rows, $fmtMin) {
            $h = fopen('php://output', 'w');
            fputs($h, "\xEF\xBB\xBF");
            fputcsv($h, [
                'ID', 'Nome', 'Matrícula', 'CPF', 'PIS', 'Cargo', 'Departamento', 'Empresa',
                'Admissão', 'Hs/Sem', 'Dias Trabalhados', 'Dias Falta',
                'H. Trabalhadas', 'H. Extras', 'HE 50%', 'HE 100%', 'Ad. Noturno', 'H. Falta',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($h, [
                    $row['id'],
                    $row['nome'],
                    $row['matricula'],
                    $row['cpf'],
                    $row['pis'],
                    $row['cargo'],
                    $row['departamento'],
                    $row['empresa'],
                    $row['admissao'],
                    $row['horas_semana'],
                    $row['dias_trabalhados'],
                    $row['dias_falta'],
                    $fmtMin($row['trabalhado_min']),
                    $fmtMin($row['extra_min']),
                    $fmtMin($row['extra_50_min']),
                    $fmtMin($row['extra_100_min']),
                    $fmtMin($row['extra_noc_min']),
                    $fmtMin($row['falta_min']),
                ], ';');
            }
            fclose($h);
        };

        return response()->stream($callback, 200, $headers);
    }
}
