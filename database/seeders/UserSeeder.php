<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@ponto.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'active' => true,
            ]
        );

        // Gestor
        $gestor = User::firstOrCreate(
            ['email' => 'gestor@ponto.com'],
            [
                'name' => 'Gestor RH',
                'password' => Hash::make('password'),
                'role' => 'gestor',
                'active' => true,
            ]
        );

        if (!$gestor->employee && $company) {
            $emp = Employee::create([
                'user_id' => $gestor->id,
                'company_id' => $company->id,
                'cpf' => '111.111.111-11',
                'cargo' => 'Gestor de RH',
                'department' => 'Recursos Humanos',
                'admission_date' => now()->subYears(2)->toDateString(),
                'contract_type' => 'clt',
                'weekly_hours' => 44,
                'active' => true,
            ]);

            WorkSchedule::create([
                'employee_id' => $emp->id,
                'name' => 'Jornada Padrão',
                'entry_time' => '08:00:00',
                'lunch_start' => '12:00:00',
                'lunch_end' => '13:00:00',
                'exit_time' => '17:48:00',
                'tolerance_minutes' => 10,
                'work_days' => [1, 2, 3, 4, 5],
                'active' => true,
            ]);
        }

        // Funcionário de teste
        $funcionario = User::firstOrCreate(
            ['email' => 'funcionario@ponto.com'],
            [
                'name' => 'João da Silva',
                'password' => Hash::make('password'),
                'role' => 'funcionario',
                'active' => true,
            ]
        );

        if (!$funcionario->employee && $company) {
            $emp = Employee::create([
                'user_id' => $funcionario->id,
                'company_id' => $company->id,
                'cpf' => '222.222.222-22',
                'cargo' => 'Desenvolvedor',
                'department' => 'TI',
                'registration_number' => '001',
                'admission_date' => now()->subYear()->toDateString(),
                'contract_type' => 'clt',
                'weekly_hours' => 44,
                'active' => true,
            ]);

            WorkSchedule::create([
                'employee_id' => $emp->id,
                'name' => 'Jornada Padrão',
                'entry_time' => '09:00:00',
                'lunch_start' => '12:00:00',
                'lunch_end' => '13:00:00',
                'exit_time' => '18:48:00',
                'tolerance_minutes' => 10,
                'work_days' => [1, 2, 3, 4, 5],
                'active' => true,
            ]);
        }

        $this->command->info('Usuários criados:');
        $this->command->table(
            ['E-mail', 'Senha', 'Perfil'],
            [
                ['admin@ponto.com', 'password', 'admin'],
                ['gestor@ponto.com', 'password', 'gestor'],
                ['funcionario@ponto.com', 'password', 'funcionario'],
            ]
        );
    }
}
