<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\TimeRecord;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-employees');

        $search = $request->get('q');
        $status = $request->get('status', 'active');

        $employees = Employee::with('user', 'company', 'dept')
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
        $companies   = Company::where('active', true)->orderBy('name')->get();
        $departments = $this->departmentsForForm();

        return view('web.employees.create', compact('companies', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email',
            'cpf'               => 'required|string|size:14|unique:employees,cpf',
            'cargo'             => 'required|string|max:100',
            'company_id'        => 'required|exists:companies,id',
            'department_id'     => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', (int) $request->company_id)),
            ],
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
            $dept = $request->filled('department_id')
                ? Department::find($request->integer('department_id'))
                : null;

            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->get('password', Str::random(12))),
                'role'     => 'funcionario',
                'active'   => true,
            ]);
            Employee::create([
                'user_id'             => $user->id,
                'company_id'          => $request->company_id,
                'department_id'       => $dept?->id,
                'cpf'                 => $request->cpf,
                'cargo'               => $request->cargo,
                'department'          => $dept?->name,
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

        $employee->load('user', 'company', 'workSchedule', 'dept');

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
        $employee->load('user', 'company', 'workSchedule', 'dept');
        $companies   = Company::where('active', true)->orderBy('name')->get();
        $departments = $this->departmentsForForm();

        return view('web.employees.edit', compact('employee', 'companies', 'departments'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manage-employees');

        $isPending = $employee->user?->access_pending;

        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $employee->user_id,
            'cpf'             => 'nullable|string|size:14|unique:employees,cpf,' . $employee->id,
            'cargo'           => 'required|string|max:100',
            'company_id'      => 'required|exists:companies,id',
            'department_id'   => [
                'nullable',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', (int) $request->company_id)),
            ],
            'admission_date'  => 'required|date',
            'contract_type'   => 'required|in:clt,pj,estagio,temporario',
            'weekly_hours'    => 'required|integer|min:1|max:60',
            'registration_number' => 'nullable|string|max:50',
            'pis'             => 'nullable|string|max:20',
            'ws_entry_time'    => 'nullable|date_format:H:i',
            'ws_exit_time'     => 'nullable|date_format:H:i',
            'ws_lunch_minutes' => 'nullable|integer|min:0|max:480',
            'ws_tolerance'     => 'nullable|integer|min:0|max:60',
            'ws_work_days'     => 'nullable|array',
            'ws_work_days.*'   => 'integer|min:0|max:6',
        ]);

        DB::transaction(function () use ($request, $employee, $isPending) {
            $dept = $request->filled('department_id')
                ? Department::find($request->integer('department_id'))
                : null;

            $userUpdate = [
                'name'  => $request->name,
                'email' => $request->email,
            ];

            // Se estava com acesso pendente e agora tem um e-mail real definido,
            // libera o acesso ao app
            if ($isPending && $request->filled('email')
                && !str_ends_with($request->email, '@importado.local')) {
                $userUpdate['access_pending'] = false;
                $userUpdate['active']         = true;
            }

            $employee->user->update($userUpdate);

            $employee->update([
                'company_id'          => $request->company_id,
                'department_id'       => $dept?->id,
                'cpf'                 => $request->cpf ?: null,
                'cargo'               => $request->cargo,
                'department'          => $dept?->name,
                'registration_number' => $request->registration_number,
                'admission_date'      => $request->admission_date,
                'contract_type'       => $request->contract_type,
                'weekly_hours'        => $request->weekly_hours,
                'pis'                 => $request->pis,
            ]);

            if ($request->filled('ws_entry_time')) {
                $lunchMinutes = $request->filled('ws_lunch_minutes')
                    ? (int) $request->ws_lunch_minutes
                    : null;

                WorkSchedule::updateOrCreate(
                    ['employee_id' => $employee->id],
                    [
                        'name'              => 'Escala padrão',
                        'entry_time'        => $request->ws_entry_time,
                        'exit_time'         => $request->ws_exit_time,
                        'lunch_minutes'     => $lunchMinutes,
                        'tolerance_minutes' => $request->ws_tolerance ?? 5,
                        'work_days'         => array_map('intval', $request->ws_work_days ?? [1,2,3,4,5]),
                        'active'            => true,
                        'notify_late'       => $request->boolean('ws_notify_late'),
                        'notify_absence'    => $request->boolean('ws_notify_absence'),
                        'notify_overtime'   => $request->boolean('ws_notify_overtime'),
                    ]
                );
            }
        });

        $msg = 'Colaborador atualizado com sucesso.';
        if ($isPending && !str_ends_with($request->email, '@importado.local')) {
            $msg = 'Colaborador atualizado. Acesso ao app liberado — defina a senha na seção abaixo.';
        }

        return redirect()->route('painel.employees.edit', $employee)
            ->with('success', $msg);
    }

    public function toggle(Employee $employee): RedirectResponse
    {
        $this->authorize('manage-employees');

        $employee->update(['active' => !$employee->active]);
        $msg = $employee->active ? 'Colaborador reativado.' : 'Colaborador desativado.';

        return back()->with('success', $msg);
    }

    public function resetPassword(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.required'  => 'A nova senha é obrigatória.',
            'password.min'       => 'A senha deve ter pelo menos 6 caracteres.',
            'password.confirmed' => 'As senhas não coincidem.',
        ]);

        $employee->user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('painel.employees.edit', $employee)
            ->with('success', 'Senha do colaborador redefinida com sucesso.');
    }

    public function export(Request $request)
    {
        $this->authorize('manage-employees');

        $status = $request->get('status', 'active');

        $employees = Employee::with('user', 'company', 'dept')
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
                    $emp->dept?->name ?? $emp->department ?? '',
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
                    $deptName = $line['departamento'] ?? null;
                    $deptId   = null;
                    if ($deptName) {
                        $deptId = Department::where('company_id', $companyId)->where('name', $deptName)->value('id');
                    }

                    $user = User::create([
                        'name'     => $line['nome'],
                        'email'    => $line['email'],
                        'password' => Hash::make($line['senha'] ?? Str::random(10)),
                        'role'     => 'funcionario',
                        'active'   => true,
                    ]);
                    Employee::create([
                        'user_id'             => $user->id,
                        'company_id'          => $companyId,
                        'department_id'       => $deptId,
                        'cpf'                 => $line['cpf'],
                        'cargo'               => $line['cargo'] ?? 'A definir',
                        'department'          => $deptName,
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

    /**
     * Importa colaboradores de arquivo legado com formato:
     * pis;nome;administrador;matricula;rfid;codigo;senha;barras;digitais
     */
    public function importFromLegacy(Request $request): RedirectResponse
    {
        $this->authorize('manage-employees');

        $request->validate([
            'file'       => 'required|file|mimes:txt,csv|max:10240',
            'company_id' => 'required|exists:companies,id',
        ], [
            'file.required'       => 'Selecione o arquivo exportado do sistema legado.',
            'company_id.required' => 'Selecione a empresa para os colaboradores.',
            'company_id.exists'   => 'Empresa inválida.',
        ]);

        $file      = $request->file('file');
        $companyId = $request->company_id;
        $handle    = fopen($file->getRealPath(), 'r');

        // Remover BOM se existir
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Primeira linha é o cabeçalho: pis;nome;administrador;matricula;rfid;codigo;senha;barras;digitais
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'Arquivo inválido ou vazio.');
        }
        $header = array_map(function ($val) {
            $val = trim($val);
            if (!mb_detect_encoding($val, 'UTF-8', true)) {
                $val = mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
            }
            return strtolower($val);
        }, $header);

        // Validar que é o formato legado esperado
        if (!in_array('pis', $header) || !in_array('nome', $header)) {
            fclose($handle);
            return back()->with('error', 'Formato de arquivo não reconhecido. O arquivo deve ter as colunas "pis" e "nome".');
        }

        $errors  = [];
        $notices = [];
        $success = 0;
        $skipped = 0;
        $row     = 1;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $row++;
            if (count($data) < 2) {
                continue;
            }

            // Converter encoding: o arquivo legado pode estar em Latin-1/Windows-1252
            $data = array_map(function ($val) {
                if (!mb_detect_encoding($val, 'UTF-8', true)) {
                    return mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
                }
                return $val;
            }, $data);

            // Mapear colunas disponíveis
            $colCount = min(count($header), count($data));
            $line     = array_combine(
                array_slice($header, 0, $colCount),
                array_map('trim', array_slice($data, 0, $colCount))
            );

            $nome       = $line['nome']      ?? '';
            $pis        = $line['pis']       ?? '';
            $matricula  = $line['matricula'] ?? '';

            // Ignorar linhas sem nome ou PIS
            if (empty($nome) || empty($pis)) {
                continue;
            }

            // Ignorar registros de teste: nome muito curto (1-2 caracteres)
            if (mb_strlen($nome) <= 2) {
                $skipped++;
                continue;
            }

            // Ignorar PIS inválido: PIS brasileiro tem exatamente 11 dígitos
            $pisDigitos = preg_replace('/\D/', '', $pis);
            if (strlen($pisDigitos) !== 11) {
                $skipped++;
                continue;
            }

            // Ignorar administradores (campo administrador = 1)
            if (($line['administrador'] ?? '0') === '1') {
                $skipped++;
                continue;
            }

            // Verificar se PIS já existe
            if (Employee::where('pis', $pis)->exists()) {
                $skipped++;
                continue;
            }

            try {
                DB::transaction(function () use ($nome, $pis, $matricula, $companyId) {
                    // Cria user com email placeholder e sem senha definida
                    // access_pending = true indica que o admin precisa configurar o acesso
                    $emailPlaceholder = 'pendente_' . Str::slug($pis) . '@importado.local';
                    $user = User::create([
                        'name'           => $nome,
                        'email'          => $emailPlaceholder,
                        'password'       => Hash::make(Str::random(32)),
                        'role'           => 'funcionario',
                        'active'         => false,
                        'access_pending' => true,
                        'company_id'     => $companyId,
                    ]);

                    Employee::create([
                        'user_id'             => $user->id,
                        'company_id'          => $companyId,
                        'cpf'                 => null,
                        'cargo'               => 'A definir',
                        'registration_number' => $matricula ?: null,
                        'admission_date'      => now()->toDateString(),
                        'contract_type'       => 'clt',
                        'weekly_hours'        => 44,
                        'pis'                 => $pis,
                        'active'              => true,
                    ]);
                });
                $success++;
            } catch (\Throwable $e) {
                $errors[] = "Linha {$row} ({$nome}): {$e->getMessage()}";
            }
        }
        fclose($handle);

        $msg = "Importação legada concluída: {$success} colaborador(es) criado(s)";
        if ($skipped > 0) {
            $msg .= ", {$skipped} já existiam e foram ignorados";
        }
        $msg .= '.';

        if ($errors) {
            session()->flash('import_errors', $errors);
        }

        return redirect()->route('painel.employees.index')->with('success', $msg);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Department>
     */
    private function departmentsForForm()
    {
        return Department::query()
            ->with('company')
            ->where('active', true)
            ->when(auth()->user()->isGestor() && auth()->user()->company_id, function ($q) {
                $q->where('company_id', auth()->user()->company_id);
            })
            ->orderBy('name')
            ->get();
    }
}
