<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EditRequestWebController;
use App\Http\Controllers\Web\EmployeeWebController;
use App\Http\Controllers\Web\LoginController;
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
        Route::get('/colaboradores/criar', [EmployeeWebController::class, 'create'])->name('employees.create');
        Route::post('/colaboradores', [EmployeeWebController::class, 'store'])->name('employees.store');
        Route::get('/colaboradores/{employee}', [EmployeeWebController::class, 'show'])->name('employees.show');
        Route::get('/colaboradores/{employee}/editar', [EmployeeWebController::class, 'edit'])->name('employees.edit');
        Route::put('/colaboradores/{employee}', [EmployeeWebController::class, 'update'])->name('employees.update');
        Route::patch('/colaboradores/{employee}/toggle', [EmployeeWebController::class, 'toggle'])->name('employees.toggle');

        // Pontos
        Route::get('/pontos', [TimeRecordWebController::class, 'index'])->name('pontos.index');
        Route::get('/pontos/exportar', [TimeRecordWebController::class, 'export'])->name('pontos.export');

        // Utilizadores (apenas admin)
        Route::get('/utilizadores', [UserWebController::class, 'index'])->name('users.index');
        Route::get('/utilizadores/criar', [UserWebController::class, 'create'])->name('users.create');
        Route::post('/utilizadores', [UserWebController::class, 'store'])->name('users.store');
        Route::get('/utilizadores/{user}/editar', [UserWebController::class, 'edit'])->name('users.edit');
        Route::put('/utilizadores/{user}', [UserWebController::class, 'update'])->name('users.update');
        Route::patch('/utilizadores/{user}/senha', [UserWebController::class, 'resetPassword'])->name('users.reset-password');
    });
});
