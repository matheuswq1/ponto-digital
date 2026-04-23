<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\TimeRecordEdit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $pendingEdits    = 0;
        $employeesCount  = 0;
        $todayCount      = 0;
        $todayTotal      = 0;
        $weekChart       = [];
        $recentRecords   = collect();

        if ($user->isAdmin() || $user->isGestor()) {
            $pendingEdits   = TimeRecordEdit::where('status', 'pendente')->count();
            $employeesCount = Employee::where('active', true)->count();

            // Pontos dos últimos 7 dias para mini-gráfico
            for ($i = 6; $i >= 0; $i--) {
                $day = Carbon::today()->subDays($i);
                $weekChart[] = [
                    'label' => $day->locale('pt_BR')->isoFormat('ddd D'),
                    'count' => TimeRecord::whereDate('datetime', $day)->count(),
                ];
            }

            // Pontos registados hoje
            $todayTotal = TimeRecord::whereDate('datetime', today())->count();

            // Últimos 10 registros
            $recentRecords = TimeRecord::with('employee.user')
                ->whereDate('datetime', today())
                ->orderByDesc('datetime')
                ->limit(10)
                ->get();
        }

        if ($user->employee) {
            $todayCount = TimeRecord::where('employee_id', $user->employee->id)
                ->whereDate('datetime', today())
                ->count();
        }

        $chartMax = collect($weekChart)->max('count') ?: 1;

        return view('web.dashboard', compact(
            'pendingEdits',
            'todayCount',
            'todayTotal',
            'employeesCount',
            'weekChart',
            'chartMax',
            'recentRecords',
        ));
    }
}
