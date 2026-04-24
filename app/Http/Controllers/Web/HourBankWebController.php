<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\HourBankRequest;
use App\Models\HourBankTransaction;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HourBankWebController extends Controller
{
    public function __construct(private PushNotificationService $push) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $status    = $request->get('status', 'pendente');
        $companyId = $request->get('company_id');

        $requests = HourBankRequest::with(['employee.user', 'employee.company', 'approvedBy'])
            ->when($status !== 'todos', fn ($q) => $q->where('status', $status))
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $companies = Company::where('active', true)->orderBy('name')->get();

        return view('web.hour-bank.index', compact('requests', 'status', 'companies', 'companyId'));
    }

    public function approve(Request $request, HourBankRequest $hourBankRequest): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        if (! $hourBankRequest->isPending()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $employee = $hourBankRequest->employee;
        $balance  = $employee->hour_bank_balance_minutes;

        if (abs($hourBankRequest->minutes_requested) > $balance) {
            return back()->with('error', 'Saldo insuficiente. Saldo atual: ' . $employee->hour_bank_balance_formatted);
        }

        $hourBankRequest->approve($request->user(), $request->get('notes'));

        $this->push->sendToEmployee($employee, [
            'title' => 'Folga aprovada',
            'body'  => 'Sua solicitação de folga para ' . $hourBankRequest->requested_date->format('d/m/Y') . ' foi aprovada.',
            'data'  => ['type' => 'hour_bank_approved'],
        ]);

        return back()->with('success', 'Solicitação aprovada com sucesso.');
    }

    public function reject(Request $request, HourBankRequest $hourBankRequest): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'notes' => 'required|string|min:5|max:500',
        ], ['notes.required' => 'A justificativa da rejeição é obrigatória.']);

        if (! $hourBankRequest->isPending()) {
            return back()->with('error', 'Esta solicitação já foi processada.');
        }

        $hourBankRequest->reject($request->user(), $request->get('notes'));

        $this->push->sendToEmployee($hourBankRequest->employee, [
            'title' => 'Folga não aprovada',
            'body'  => 'Sua solicitação de folga para ' . $hourBankRequest->requested_date->format('d/m/Y') . ' foi rejeitada.',
            'data'  => ['type' => 'hour_bank_rejected'],
        ]);

        return back()->with('success', 'Solicitação rejeitada.');
    }

    public function employeeBalance(Employee $employee): View
    {
        $this->authorize('manage-employees');

        $employee->load('user', 'company');

        $transactions = $employee->hourBankTransactions()
            ->orderByDesc('reference_date')
            ->limit(50)
            ->get();

        $requests = $employee->hourBankRequests()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $balanceMinutes   = $employee->hour_bank_balance_minutes;
        $balanceFormatted = $employee->hour_bank_balance_formatted;

        return view('web.hour-bank.employee', compact(
            'employee', 'transactions', 'requests', 'balanceMinutes', 'balanceFormatted'
        ));
    }

    public function manualAdjust(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manage-employees');

        $data = $request->validate([
            'minutes'     => 'required|integer|not_in:0',
            'description' => 'required|string|max:200',
            'date'        => 'required|date',
        ]);

        HourBankTransaction::create([
            'employee_id'    => $employee->id,
            'type'           => 'ajuste_manual',
            'minutes'        => $data['minutes'],
            'description'    => $data['description'],
            'reference_date' => $data['date'],
            'created_by'     => $request->user()->id,
        ]);

        return back()->with('success', 'Ajuste manual registrado com sucesso.');
    }
}
