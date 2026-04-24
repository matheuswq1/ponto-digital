<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeRecord;
use Carbon\Carbon;
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
}
