<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeService
{
    public function create(array $data, Company $company): Employee
    {
        return DB::transaction(function () use ($data, $company) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? str()->random(16)),
                'role' => 'funcionario',
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'cpf' => $data['cpf'],
                'cargo' => $data['cargo'],
                'department' => $data['department'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'admission_date' => $data['admission_date'],
                'contract_type' => $data['contract_type'] ?? 'clt',
                'weekly_hours' => $data['weekly_hours'] ?? 44,
                'pis' => $data['pis'] ?? null,
            ]);

            if (isset($data['schedule'])) {
                $employee->workSchedules()->create(array_merge($data['schedule'], [
                    'name' => $data['schedule']['name'] ?? 'Jornada Padrão',
                    'active' => true,
                ]));
            }

            return $employee->load('user', 'company', 'workSchedule');
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            if (isset($data['name']) || isset($data['email'])) {
                $employee->user->update(array_filter([
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                ]));
            }

            $employee->update(array_filter([
                'cargo' => $data['cargo'] ?? null,
                'department' => $data['department'] ?? null,
                'weekly_hours' => $data['weekly_hours'] ?? null,
                'active' => $data['active'] ?? null,
            ], fn($v) => $v !== null));

            return $employee->fresh('user', 'company', 'workSchedule');
        });
    }

    public function dismiss(Employee $employee, string $dismissalDate): Employee
    {
        return DB::transaction(function () use ($employee, $dismissalDate) {
            $employee->update([
                'active' => false,
                'dismissal_date' => $dismissalDate,
            ]);

            $employee->user->update(['active' => false]);

            return $employee->fresh();
        });
    }
}
