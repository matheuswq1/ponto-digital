<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\FaceController;
use App\Http\Controllers\Api\HourBankController;
use App\Http\Controllers\Api\TimeRecordController;
use App\Http\Controllers\Api\TimeRecordEditController;
use App\Http\Controllers\Api\TotemController;
use App\Http\Controllers\Api\WorkDayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas Públicas
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])->name('api.login');

    /*
    |--------------------------------------------------------------------------
    | Rotas Autenticadas
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // Autenticação
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('api.me');
        Route::post('/refresh-token', [AuthController::class, 'refreshToken'])->name('api.refresh-token');
        Route::post('/device-tokens', [DeviceTokenController::class, 'store'])->name('api.device-tokens.store');
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy'])->name('api.device-tokens.destroy');

        // Reconhecimento facial
        Route::prefix('face')->group(function () {
            Route::post('/enroll', [FaceController::class, 'enroll'])->name('api.face.enroll');
            Route::post('/verify', [FaceController::class, 'verify'])->name('api.face.verify');
            Route::delete('/enroll', [FaceController::class, 'deleteEnroll'])->name('api.face.delete');
        });

        // Ponto
        Route::prefix('time-records')->group(function () {
            Route::get('/', [TimeRecordController::class, 'index'])->name('api.time-records.index');
            Route::post('/', [TimeRecordController::class, 'store'])->name('api.time-records.store');
            Route::get('/today', [TimeRecordController::class, 'today'])->name('api.time-records.today');
            Route::get('/signed-upload-url', [TimeRecordController::class, 'getSignedUploadUrl'])->name('api.time-records.signed-url');
            Route::post('/sync-offline', [TimeRecordController::class, 'syncOffline'])->name('api.time-records.sync-offline');
            Route::get('/{timeRecord}', [TimeRecordController::class, 'show'])->name('api.time-records.show');

            // Edições/Correções de ponto
            Route::post('/{timeRecord}/edit-request', [TimeRecordEditController::class, 'store'])->name('api.time-records.edit-request');
        });

        // Solicitações de correção (gestores/admin)
        Route::prefix('edit-requests')->group(function () {
            Route::get('/', [TimeRecordEditController::class, 'index'])->name('api.edit-requests.index');
            Route::post('/{edit}/approve', [TimeRecordEditController::class, 'approve'])
                ->middleware('can:approve-edit-requests')
                ->name('api.edit-requests.approve');
            Route::post('/{edit}/reject', [TimeRecordEditController::class, 'reject'])
                ->middleware('can:approve-edit-requests')
                ->name('api.edit-requests.reject');
        });

        // Banco de horas — solicitações de folga
        Route::prefix('hour-bank')->group(function () {
            Route::get('/balance', [HourBankController::class, 'balance'])->name('api.hour-bank.balance');
            Route::get('/transactions', [HourBankController::class, 'transactions'])->name('api.hour-bank.transactions');
            Route::get('/requests', [HourBankController::class, 'requests'])->name('api.hour-bank.requests');
            Route::post('/requests', [HourBankController::class, 'storeRequest'])->name('api.hour-bank.requests.store');
        });

        // Dias de trabalho / banco de horas
        Route::prefix('work-days')->group(function () {
            Route::get('/', [WorkDayController::class, 'index'])->name('api.work-days.index');
            Route::get('/balance', [WorkDayController::class, 'balance'])->name('api.work-days.balance');
            Route::post('/employees/{employee}/{date}/recalculate', [WorkDayController::class, 'recalculate'])
                ->middleware('can:manage-employees')
                ->name('api.work-days.recalculate');
        });

        // Funcionários (admin/gestor)
        Route::middleware('can:manage-employees')->group(function () {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('api.employees.index');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('api.employees.store');
            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('api.employees.show');
            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('api.employees.update');
            Route::post('/employees/{employee}/dismiss', [EmployeeController::class, 'dismiss'])->name('api.employees.dismiss');
        });

        // Totem — dispositivo fixo de identificação facial
        Route::prefix('totem')->middleware('totem')->group(function () {
            Route::post('/identify', [TotemController::class, 'identify'])->name('api.totem.identify');
            Route::post('/register-point', [TotemController::class, 'registerPoint'])->name('api.totem.register-point');
        });

        // Empresas (somente admin)
        Route::middleware('can:manage-companies')->group(function () {
            Route::get('/companies', [CompanyController::class, 'index'])->name('api.companies.index');
            Route::post('/companies', [CompanyController::class, 'store'])->name('api.companies.store');
            Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('api.companies.show');
            Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('api.companies.update');
        });
    });
});
