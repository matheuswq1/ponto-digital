<?php

use App\Http\Controllers\Web\CompanyWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DepartmentWebController;
use App\Http\Controllers\Web\EditRequestWebController;
use App\Http\Controllers\Web\EmployeeWebController;
use App\Http\Controllers\Web\HourBankWebController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\ReportWebController;
use App\Http\Controllers\Web\TimeRecordWebController;
use App\Http\Controllers\Web\UserWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::prefix('painel')->name('painel.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/solicitacoes', [EditRequestWebController::class, 'index'])->name('edit-requests.index');
        Route::post('/solicitacoes/{edit}/aprovar', [EditRequestWebController::class, 'approve'])->name('edit-requests.approve');
        Route::post('/solicitacoes/{edit}/rejeitar', [EditRequestWebController::class, 'reject'])->name('edit-requests.reject');

        // Colaboradores
        Route::get('/colaboradores', [EmployeeWebController::class, 'index'])->name('employees.index');
        Route::get('/colaboradores/exportar', [EmployeeWebController::class, 'export'])->name('employees.export');
        Route::get('/colaboradores/importar/template', [EmployeeWebController::class, 'importTemplate'])->name('employees.import.template');
        Route::post('/colaboradores/importar', [EmployeeWebController::class, 'import'])->name('employees.import');
        Route::post('/colaboradores/importar-legado', [EmployeeWebController::class, 'importFromLegacy'])->name('employees.import.legacy');
        Route::get('/colaboradores/criar', [EmployeeWebController::class, 'create'])->name('employees.create');
        Route::post('/colaboradores', [EmployeeWebController::class, 'store'])->name('employees.store');
        Route::get('/colaboradores/{employee}', [EmployeeWebController::class, 'show'])->name('employees.show');
        Route::get('/colaboradores/{employee}/editar', [EmployeeWebController::class, 'edit'])->name('employees.edit');
        Route::match(['PUT', 'POST'], '/colaboradores/{employee}/atualizar', [EmployeeWebController::class, 'update'])->name('employees.update');
        Route::post('/colaboradores/{employee}/toggle', [EmployeeWebController::class, 'toggle'])->name('employees.toggle');
        Route::post('/colaboradores/{employee}/senha', [EmployeeWebController::class, 'resetPassword'])->name('employees.reset-password');

        // Departamentos
        Route::get('/departamentos', [DepartmentWebController::class, 'index'])->name('departments.index');
        Route::get('/departamentos/criar', [DepartmentWebController::class, 'create'])->name('departments.create');
        Route::post('/departamentos', [DepartmentWebController::class, 'store'])->name('departments.store');
        Route::get('/departamentos/{department}/editar', [DepartmentWebController::class, 'edit'])->name('departments.edit');
        Route::post('/departamentos/{department}/atualizar', [DepartmentWebController::class, 'update'])->name('departments.update');
        Route::get('/departamentos/{department}/dados-escala', [DepartmentWebController::class, 'dadosEscala'])->name('departments.dados-escala');

        // Pontos
        Route::get('/pontos', [TimeRecordWebController::class, 'index'])->name('pontos.index');
        Route::get('/pontos/exportar', [TimeRecordWebController::class, 'export'])->name('pontos.export');
        Route::get('/pontos/cartao', [TimeRecordWebController::class, 'cartaoPonto'])->name('pontos.cartao');

        // Relatórios
        Route::get('/relatorios/folha-pagamento', [ReportWebController::class, 'folhaPagamento'])->name('reports.folha-pagamento');
        Route::get('/relatorios/presenca', [ReportWebController::class, 'presenca'])->name('reports.presenca');

        // Banco de Horas
        Route::get('/banco-horas', [HourBankWebController::class, 'index'])->name('hour-bank.index');
        Route::post('/banco-horas/{hourBankRequest}/aprovar', [HourBankWebController::class, 'approve'])->name('hour-bank.approve');
        Route::post('/banco-horas/{hourBankRequest}/rejeitar', [HourBankWebController::class, 'reject'])->name('hour-bank.reject');
        Route::get('/banco-horas/colaborador/{employee}', [HourBankWebController::class, 'employeeBalance'])->name('hour-bank.employee');
        Route::post('/banco-horas/colaborador/{employee}/ajuste', [HourBankWebController::class, 'manualAdjust'])->name('hour-bank.adjust');

        // Empresas (apenas admin)
        Route::get('/empresas', [CompanyWebController::class, 'index'])->name('companies.index');
        Route::get('/empresas/criar', [CompanyWebController::class, 'create'])->name('companies.create');
        Route::post('/empresas', [CompanyWebController::class, 'store'])->name('companies.store');
        Route::get('/empresas/{company}', [CompanyWebController::class, 'show'])->name('companies.show');
        Route::get('/empresas/{company}/editar', [CompanyWebController::class, 'edit'])->name('companies.edit');
        Route::post('/empresas/{company}/atualizar', [CompanyWebController::class, 'update'])->name('companies.update');
        Route::post('/empresas/{company}/gestores', [CompanyWebController::class, 'addGestor'])->name('companies.gestores.add');
        Route::post('/empresas/{company}/gestores/{gestor}/atualizar', [CompanyWebController::class, 'updateGestor'])->name('companies.gestores.update');
        Route::post('/empresas/{company}/gestores/{gestor}/senha', [CompanyWebController::class, 'resetGestorPassword'])->name('companies.gestores.password');
        Route::post('/empresas/{company}/totems', [CompanyWebController::class, 'addTotem'])->name('companies.totems.add');
        Route::post('/empresas/{company}/totems/{totem}/toggle', [CompanyWebController::class, 'toggleTotem'])->name('companies.totems.toggle');
        Route::post('/empresas/{company}/totems/{totem}/senha', [CompanyWebController::class, 'resetTotemPassword'])->name('companies.totems.password');

        // Utilizadores (apenas admin)
        Route::get('/utilizadores', [UserWebController::class, 'index'])->name('users.index');
        Route::get('/utilizadores/criar', [UserWebController::class, 'create'])->name('users.create');
        Route::post('/utilizadores', [UserWebController::class, 'store'])->name('users.store');
        Route::get('/utilizadores/{user}/editar', [UserWebController::class, 'edit'])->name('users.edit');
        Route::match(['PUT', 'POST'], '/utilizadores/{user}/atualizar', [UserWebController::class, 'update'])->name('users.update');
        Route::post('/utilizadores/{user}/senha', [UserWebController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('/utilizadores/{user}', [UserWebController::class, 'destroy'])->name('users.destroy');
    });
});
