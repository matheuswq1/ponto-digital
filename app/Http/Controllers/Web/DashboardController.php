<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HourBankRequest;
use App\Models\TimeRecord;
use App\Models\TimeRecordEdit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $tz   = config('app.timezone', 'America/Sao_Paulo');
        $now  = Carbon::now($tz);

        $period = $request->get('period', '7d');

        switch ($period) {
            case 'today':
                $rangeStart = $now->copy()->startOfDay();
                $rangeEnd   = $now->copy()->endOfDay();
                break;
            case '30d':
                $rangeStart = $now->copy()->subDays(29)->startOfDay();
                $rangeEnd   = $now->copy()->endOfDay();
                break;
            case 'month':
                $rangeStart = $now->copy()->startOfMonth()->startOfDay();
                $rangeEnd   = $now->copy()->endOfMonth()->endOfDay();
                break;
            case 'custom':
                $df = $request->get('date_from', $now->toDateString());
                $dt = $request->get('date_to', $now->toDateString());
                $rangeStart = Carbon::createFromFormat('Y-m-d', $df, $tz)->startOfDay();
                $rangeEnd   = Carbon::createFromFormat('Y-m-d', $dt, $tz)->endOfDay();
                if ($rangeStart->gt($rangeEnd)) {
                    [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
                }
                break;
            case '7d':
            default:
                $period = '7d';
                $rangeStart = $now->copy()->subDays(6)->startOfDay();
                $rangeEnd   = $now->copy()->endOfDay();
        }

        $rangeStartUtc = $rangeStart->copy()->utc();
        $rangeEndUtc   = $rangeEnd->copy()->utc();

        $companyId = ($user->isGestor() && $user->company_id) ? $user->company_id : null;
        if ($user->isAdmin() && $request->filled('company_id')) {
            $companyId = (int) $request->get('company_id') ?: null;
        }

        $pendingEdits    = 0;
        $employeesCount  = 0;
        $todayCount      = 0;
        $recordsInRange  = 0;
        $uniqueEmployees = 0;
        $weekChart       = [];
        $recentRecords   = collect();
        $deptStats       = collect();
        $pendingHourBank = 0;
        $absentsEndDay   = 0;
        $companies       = collect();

        if ($user->isAdmin() || $user->isGestor()) {
            $empBase = Employee::query()->where('active', true)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

            $employeesCount = (clone $empBase)->count();

            $pendingEdits = TimeRecordEdit::where('status', 'pendente')->count();

            $trBase = TimeRecord::query()
                ->whereBetween('datetime', [$rangeStartUtc, $rangeEndUtc])
                ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)));

            $recordsInRange = (clone $trBase)->count();
            $uniqueEmployees = (int) (clone $trBase)
                ->selectRaw('COUNT(DISTINCT employee_id) as aggregate')
                ->value('aggregate');

            // Gráfico: diário se ≤ 45 dias no período; senão agrega por semana
            $daysDiff = $rangeStart->diffInDays($rangeEnd) + 1;
            if ($daysDiff <= 45) {
                foreach (CarbonPeriod::create($rangeStart->toDateString(), $rangeEnd->toDateString()) as $d) {
                    $day = $d->copy();
                    $dayS = $day->copy()->startOfDay()->utc();
                    $dayE = $day->copy()->endOfDay()->utc();
                    $weekChart[] = [
                        'label' => $day->locale('pt_BR')->isoFormat('D MMM'),
                        'count' => TimeRecord::query()
                            ->whereBetween('datetime', [$dayS, $dayE])
                            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
                            ->count(),
                    ];
                }
            } else {
                $cur = $rangeStart->copy()->startOfWeek();
                while ($cur->lte($rangeEnd)) {
                    $wEnd = $cur->copy()->endOfWeek();
                    if ($wEnd->gt($rangeEnd)) {
                        $wEnd = $rangeEnd->copy();
                    }
                    $c = TimeRecord::whereBetween('datetime', [
                        $cur->copy()->utc(),
                        $wEnd->copy()->endOfDay()->utc(),
                    ])
                        ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
                        ->count();
                    $weekChart[] = [
                        'label' => $cur->format('d/m').' – '.$wEnd->format('d/m'),
                        'count' => $c,
                    ];
                    $cur->addWeek();
                }
            }

            $recentRecords = TimeRecord::with('employee.user')
                ->whereBetween('datetime', [$rangeStartUtc, $rangeEndUtc])
                ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
                ->orderByDesc('datetime')
                ->limit(12)
                ->get();

            $deptQuery = Department::withCount(['employees' => fn ($q) => $q->where('active', true)])
                ->with('company')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('name');

            $deptStats = $deptQuery->get()->map(function ($dept) use ($rangeStartUtc, $rangeEndUtc, $companyId) {
                $empIds = $dept->employees()->where('active', true)->pluck('id');
                if ($empIds->isEmpty()) {
                    return [
                        'name'    => $dept->name,
                        'company' => $dept->company?->name,
                        'total'   => 0,
                        'ponto'   => 0,
                        'ausentes'=> 0,
                    ];
                }
                $comPonto = (int) TimeRecord::query()
                    ->whereIn('employee_id', $empIds)
                    ->whereBetween('datetime', [$rangeStartUtc, $rangeEndUtc])
                    ->selectRaw('COUNT(DISTINCT employee_id) as c')
                    ->value('c');

                return [
                    'name'    => $dept->name,
                    'company' => $dept->company?->name,
                    'total'   => $dept->employees_count,
                    'ponto'   => $comPonto,
                    'ausentes'=> max(0, $dept->employees_count - $comPonto),
                ];
            });

            $pendingHourBank = HourBankRequest::where('status', 'pendente')
                ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
                ->count();

            $absentDay = $rangeEnd->toDateString();
            $absentsEndDay = Employee::where('active', true)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where(fn ($q) => $q
                    ->whereNull('admission_date')
                    ->orWhere('admission_date', '<=', $absentDay))
                ->whereDoesntHave('timeRecords', fn ($q) => $q->whereDate('datetime', $absentDay))
                ->count();

            if ($user->isAdmin()) {
                $companies = \App\Models\Company::orderBy('name')->get();
            }
        }

        if ($user->employee) {
            $todayCount = TimeRecord::where('employee_id', $user->employee->id)
                ->whereDate('datetime', today())
                ->count();
        }

        $chartMax = collect($weekChart)->max('count') ?: 1;

        $dateFromParam = $request->get('date_from', $rangeStart->toDateString());
        $dateToParam   = $request->get('date_to', $rangeEnd->toDateString());

        return view('web.dashboard', compact(
            'pendingEdits',
            'todayCount',
            'recordsInRange',
            'uniqueEmployees',
            'employeesCount',
            'weekChart',
            'chartMax',
            'recentRecords',
            'deptStats',
            'pendingHourBank',
            'absentsEndDay',
            'period',
            'rangeStart',
            'rangeEnd',
            'dateFromParam',
            'dateToParam',
            'companyId',
            'companies',
        ));
    }
}
