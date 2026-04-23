<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'cpf' => $this->generateCpf(),
            'cargo' => fake()->jobTitle(),
            'department' => fake()->randomElement(['TI', 'RH', 'Financeiro', 'Comercial', 'Operacional']),
            'registration_number' => fake()->unique()->numerify('###'),
            'admission_date' => fake()->dateTimeBetween('-5 years', '-3 months')->format('Y-m-d'),
            'contract_type' => fake()->randomElement(['clt', 'pj', 'estagio']),
            'weekly_hours' => 44,
            'active' => true,
        ];
    }

    private function generateCpf(): string
    {
        $n = array_map(fn() => rand(0, 9), range(0, 8));
        $d1 = $this->cpfDigit($n, 10);
        $d2 = $this->cpfDigit(array_merge($n, [$d1]), 11);
        $base = implode('', $n);
        return substr($base, 0, 3) . '.' . substr($base, 3, 3) . '.' . substr($base, 6, 3) . '-' . $d1 . $d2;
    }

    private function cpfDigit(array $nums, int $weight): int
    {
        $sum = 0;
        foreach ($nums as $n) {
            $sum += $n * $weight--;
        }
        $rem = $sum % 11;
        return $rem < 2 ? 0 : 11 - $rem;
    }
}
