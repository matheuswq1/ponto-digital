<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FraudAttempt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FraudAlertWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view-audit-logs');

        $user       = $request->user();
        $isAdmin    = $user->role === 'admin';
        $companyId  = $request->get('company_id');
        $employeeId = $request->get('employee_id');
        $rule       = $request->get('rule');
        $action     = $request->get('action');
        $dateFrom   = $request->get('date_from', today()->subDays(7)->toDateString());
        $dateTo     = $request->get('date_to',   today()->toDateString());

        $query = FraudAttempt::query()
            ->with(['employee.user', 'company'])
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay(),
            ]);

        if (! $isAdmin) {
            $query->where('company_id', $user->company_id);
        } elseif ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($rule) {
            $query->where('rule_triggered', $rule);
        }
        if ($action) {
            $query->where('action_taken', $action);
        }

        $attempts = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        $companies = $isAdmin ? Company::where('active', true)->orderBy('name')->get() : collect();
        $employees = Employee::with('user')
            ->when(! $isAdmin, fn ($q) => $q->where('company_id', $user->company_id))
            ->when($isAdmin && $companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('active', true)
            ->get();

        $rulesOptions = [
            'mock_location'    => 'GPS Falso',
            'velocity_jump'    => 'Salto de Localização',
            'wifi_mismatch'    => 'Wi-Fi não autorizado',
            'ip_city_mismatch' => 'Cidade IP divergente',
        ];

        return view('web.fraud-alerts.index', compact(
            'attempts', 'companies', 'employees',
            'rulesOptions', 'companyId', 'employeeId',
            'rule', 'action', 'dateFrom', 'dateTo'
        ));
    }
}
