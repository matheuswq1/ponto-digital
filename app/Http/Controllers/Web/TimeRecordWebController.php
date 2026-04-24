<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeRecord;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
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

        $tz   = config('app.timezone', 'America/Sao_Paulo');
        $from = Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()->utc();
        $to   = Carbon::createFromFormat('Y-m-d', $dateTo,   $tz)->endOfDay()->utc();

        $records = TimeRecord::with('employee.user')
            ->whereBetween('datetime', [$from, $to])
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->orderBy('datetime')
            ->get();

        $filename = 'pontos_' . $dateFrom . '_a_' . $dateTo . '.csv';

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

    public function cartaoPonto(Request $request): View
    {
        $this->authorize('manage-employees');

        $tz         = config('app.timezone', 'America/Sao_Paulo');
        $dateFrom   = $request->get('date_from', today()->startOfMonth()->toDateString());
        $dateTo     = $request->get('date_to',   today()->endOfMonth()->toDateString());
        $employeeId = $request->get('employee_id');

        $from = Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()->utc();
        $to   = Carbon::createFromFormat('Y-m-d', $dateTo,   $tz)->endOfDay()->utc();

        $employeesQuery = Employee::with(['user', 'company', 'workSchedule'])
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

            // Agrupar batidas por dia (horário local)
            $byDay = [];
            foreach ($records as $rec) {
                $day = $rec->datetime->setTimezone($tz)->toDateString();
                $byDay[$day][] = $rec;
            }

            $ws = $emp->workSchedule;
            // Dias de trabalho configurados (0=Dom..6=Sab), default seg-sex
            $workDays = $ws?->work_days ?? [1,2,3,4,5];

            $days   = [];
            $totalWorkedMin  = 0;
            $totalExtraMin   = 0;
            $totalFaltaMin   = 0;

            $period = CarbonPeriod::create($dateFrom, $dateTo);
            foreach ($period as $date) {
                $dateStr  = $date->toDateString();
                $dayOfWeek = (int) $date->format('w'); // 0=Dom, 6=Sab
                $isWorkDay = in_array($dayOfWeek, $workDays);
                $recs     = $byDay[$dateStr] ?? [];

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

                // Calcular minutos trabalhados no dia
                $workedMin = 0;
                foreach ($entries as $idx => $ent) {
                    $sai = $exits[$idx] ?? null;
                    if ($sai) {
                        $workedMin += $ent->datetime->diffInMinutes($sai->datetime);
                    }
                }

                $expectedMin = $ws ? $ws->getExpectedMinutes() : $emp->dailyExpectedMinutes();
                $extraMin    = 0;
                $faltaMin    = 0;

                if ($isWorkDay && count($recs) > 0) {
                    $diff = $workedMin - $expectedMin;
                    if ($diff > 0)  $extraMin = $diff;
                    if ($diff < 0)  $faltaMin = abs($diff);
                    $totalWorkedMin += $workedMin;
                    $totalExtraMin  += $extraMin;
                    $totalFaltaMin  += $faltaMin;
                }

                $days[] = [
                    'date'       => $date->copy(),
                    'date_str'   => $dateStr,
                    'is_work_day'=> $isWorkDay,
                    'batidas'    => $batidas,
                    'worked_min' => $workedMin,
                    'extra_min'  => $extraMin,
                    'falta_min'  => $faltaMin,
                    'folga'      => !$isWorkDay,
                    'sem_ponto'  => $isWorkDay && count($recs) === 0,
                ];
            }

            $cards[] = [
                'employee'        => $emp,
                'days'            => $days,
                'total_worked'    => $totalWorkedMin,
                'total_extra'     => $totalExtraMin,
                'total_falta'     => $totalFaltaMin,
                'date_from'       => $dateFrom,
                'date_to'         => $dateTo,
            ];
        }

        return view('web.pontos.cartao', compact(
            'cards', 'allEmployees', 'employeeId', 'dateFrom', 'dateTo'
        ));
    }
}
