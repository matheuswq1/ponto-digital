<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EditRequestWebController;
use App\Http\Controllers\Web\EmployeeWebController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\TimeRecordWebController;
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

        Route::get('/colaboradores', [EmployeeWebController::class, 'index'])->name('employees.index');
        Route::get('/pontos', [TimeRecordWebController::class, 'index'])->name('pontos.index');
    });
});
