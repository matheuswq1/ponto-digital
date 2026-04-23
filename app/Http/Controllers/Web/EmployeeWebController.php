<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmployeeWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $search = $request->get('q');
        $status = $request->get('status', 'active');

        $employees = Employee::with('user', 'company')
            ->when($status === 'inactive', fn($q) => $q->where('active', false))
            ->when($status === 'all', fn($q) => $q)
            ->when($status === 'active' || !$status, fn($q) => $q->where('active', true))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                  ->orWhere('cpf', 'like', "%{$search}%")
                  ->orWhere('cargo', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('web.employees.index', compact('employees', 'search', 'status'));
    }

    public function create(): View
    {
        $this->authorize('manage-employees');
        $companies = Company::where('active', true)->orderBy('name')->get();
        return view('web.employees.create', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email',
            'cpf'               => 'required|string|size:14|unique:employees,cpf',
            'cargo'             => 'required|string|max:100',
            'department'        => 'nullable|string|max:100',
            'company_id'        => 'required|exists:companies,id',
            'admission_date'    => 'required|date',
            'contract_type'     => 'required|in:clt,pj,estagio,temporario',
            'weekly_hours'      => 'required|integer|min:1|max:60',
            'registration_number' => 'nullable|string|max:50',
            'pis'               => 'nullable|string|max:20',
        ], [
            'name.required'     => 'O nome é obrigatório.',
            'email.unique'      => 'Este e-mail já está em uso.',
            'cpf.unique'        => 'Este CPF já está cadastrado.',
            'cpf.size'          => 'CPF deve ter 14 caracteres (000.000.000-00).',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->get('password', Str::random(12))),
                'role'     => 'funcionario',
                'active'   => true,
            ]);
            $user->assignRole('funcionario');

            Employee::create([
                'user_id'             => $user->id,
                'company_id'          => $request->company_id,
                'cpf'                 => $request->cpf,
                'cargo'               => $request->cargo,
                'department'          => $request->department,
                'registration_number' => $request->registration_number,
                'admission_date'      => $request->admission_date,
                'contract_type'       => $request->contract_type,
                'weekly_hours'        => $request->weekly_hours,
                'pis'                 => $request->pis,
                'active'              => true,
            ]);
        });

        return redirect()->route('painel.employees.index')
            ->with('success', 'Colaborador cadastrado com sucesso.');
    }

    public function show(Request $request, Employee $employee): View
    {
        $this->authorize('manage-employees');

        $employee->load('user', 'company', 'workSchedule');

        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->get('date_to', now()->toDateString());

        $records = TimeRecord::where('employee_id', $employee->id)
            ->whereBetween(DB::raw('DATE(datetime)'), [$dateFrom, $dateTo])
            ->orderByDesc('datetime')
            ->paginate(25)
            ->withQueryString();

        $totalRecords = TimeRecord::where('employee_id', $employee->id)->count();

        return view('web.employees.show', compact('employee', 'records', 'dateFrom', 'dateTo', 'totalRecords'));
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('manage-employees');
        $employee->load('user', 'company');
        $companies = Company::where('active', true)->orderBy('name')->get();
        return view('web.employees.edit', compact('employee', 'companies'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $employee->user_id,
            'cpf'             => 'required|string|size:14|unique:employees,cpf,' . $employee->id,
            'cargo'           => 'required|string|max:100',
            'department'      => 'nullable|string|max:100',
            'company_id'      => 'required|exists:companies,id',
            'admission_date'  => 'required|date',
            'contract_type'   => 'required|in:clt,pj,estagio,temporario',
            'weekly_hours'    => 'required|integer|min:1|max:60',
            'registration_number' => 'nullable|string|max:50',
            'pis'             => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($request, $employee) {
            $employee->user->update([
                'name'  => $request->name,
                'email' => $request->email,
            ]);

            $employee->update([
                'company_id'          => $request->company_id,
                'cpf'                 => $request->cpf,
                'cargo'               => $request->cargo,
                'department'          => $request->department,
                'registration_number' => $request->registration_number,
                'admission_date'      => $request->admission_date,
                'contract_type'       => $request->contract_type,
                'weekly_hours'        => $request->weekly_hours,
                'pis'                 => $request->pis,
            ]);
        });

        return redirect()->route('painel.employees.show', $employee)
            ->with('success', 'Colaborador atualizado com sucesso.');
    }

    public function toggle(Employee $employee): RedirectResponse
    {
        $this->authorize('manage-employees');

        $employee->update(['active' => !$employee->active]);
        $msg = $employee->active ? 'Colaborador reativado.' : 'Colaborador desativado.';

        return back()->with('success', $msg);
    }

    public function export(Request $request)
    {
        $this->authorize('manage-employees');

        $status = $request->get('status', 'active');

        $employees = Employee::with('user', 'company')
            ->when($status === 'active', fn($q) => $q->where('active', true))
            ->when($status === 'inactive', fn($q) => $q->where('active', false))
            ->orderBy('id')
            ->get();

        $filename = 'colaboradores_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($employees) {
            $handle = fopen('php://output', 'w');
            // BOM para Excel reconhecer UTF-8
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'Nome', 'E-mail', 'CPF', 'Cargo', 'Departamento',
                'Empresa', 'Contrato', 'Horas/Sem', 'Matrícula', 'PIS',
                'Admissão', 'Status', 'Facial',
            ], ';');

            foreach ($employees as $emp) {
                fputcsv($handle, [
                    $emp->id,
                    $emp->user->name ?? '',
                    $emp->user->email ?? '',
                    $emp->cpf,
                    $emp->cargo,
                    $emp->department ?? '',
                    $emp->company->name ?? '',
                    $emp->contract_type,
                    $emp->weekly_hours,
                    $emp->registration_number ?? '',
                    $emp->pis ?? '',
                    $emp->admission_date?->format('d/m/Y') ?? '',
                    $emp->active ? 'Ativo' : 'Inativo',
                    $emp->face_enrolled ? 'Sim' : 'Não',
                ], ';');
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importTemplate()
    {
        $this->authorize('manage-employees');

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_colaboradores.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'nome', 'email', 'cpf', 'cargo', 'departamento',
                'empresa_id', 'contrato', 'horas_semanais',
                'data_admissao', 'matricula', 'pis', 'senha',
            ], ';');
            fputcsv($handle, [
                'João Silva', 'joao@empresa.com', '000.000.000-00',
                'Analista', 'TI', '1', 'clt', '44',
                '2024-01-15', 'MAT001', '000.00000.00-0', 'senha123',
            ], ';');
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ], ['file.required' => 'Selecione um arquivo CSV.']);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        // Remover BOM se existir
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Cabeçalho
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            return back()->with('error', 'Arquivo CSV inválido ou vazio.');
        }
        $header = array_map('trim', $header);

        $errors  = [];
        $success = 0;
        $row     = 1;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $row++;
            if (count($data) < count($header)) continue;

            $line = array_combine($header, array_map('trim', $data));

            // Validações básicas por linha
            if (empty($line['nome']) || empty($line['email']) || empty($line['cpf'])) {
                $errors[] = "Linha {$row}: nome, e-mail e CPF são obrigatórios.";
                continue;
            }
            if (User::where('email', $line['email'])->exists()) {
                $errors[] = "Linha {$row}: e-mail '{$line['email']}' já existe.";
                continue;
            }
            if (Employee::where('cpf', $line['cpf'])->exists()) {
                $errors[] = "Linha {$row}: CPF '{$line['cpf']}' já cadastrado.";
                continue;
            }

            $companyId = $line['empresa_id'] ?? null;
            if (!$companyId || !Company::find($companyId)) {
                $errors[] = "Linha {$row}: empresa_id '{$companyId}' inválido.";
                continue;
            }

            try {
                DB::transaction(function () use ($line, $companyId) {
                    $user = User::create([
                        'name'     => $line['nome'],
                        'email'    => $line['email'],
                        'password' => Hash::make($line['senha'] ?? Str::random(10)),
                        'role'     => 'funcionario',
                        'active'   => true,
                    ]);
                    $user->assignRole('funcionario');

                    Employee::create([
                        'user_id'             => $user->id,
                        'company_id'          => $companyId,
                        'cpf'                 => $line['cpf'],
                        'cargo'               => $line['cargo'] ?? 'A definir',
                        'department'          => $line['departamento'] ?? null,
                        'registration_number' => $line['matricula'] ?? null,
                        'admission_date'      => $line['data_admissao'] ?? now()->toDateString(),
                        'contract_type'       => in_array($line['contrato'] ?? '', ['clt','pj','estagio','temporario'])
                                                    ? $line['contrato'] : 'clt',
                        'weekly_hours'        => (int) ($line['horas_semanais'] ?? 44),
                        'pis'                 => $line['pis'] ?? null,
                        'active'              => true,
                    ]);
                });
                $success++;
            } catch (\Throwable $e) {
                $errors[] = "Linha {$row}: erro ao importar — {$e->getMessage()}";
            }
        }
        fclose($handle);

        $msg = "Importação concluída: {$success} colaborador(es) criado(s).";
        if ($errors) {
            session()->flash('import_errors', $errors);
        }

        return redirect()->route('painel.employees.index')->with('success', $msg);
    }
}
