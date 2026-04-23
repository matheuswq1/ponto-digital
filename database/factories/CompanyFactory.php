<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'cnpj' => $this->generateCnpj(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zipcode' => fake()->postcode(),
            'active' => true,
            'require_photo' => true,
            'require_geolocation' => false,
            'work_start' => '08:00:00',
            'work_end' => '18:00:00',
            'lunch_duration' => 60,
            'geofence_radius' => 500,
        ];
    }

    private function generateCnpj(): string
    {
        $n = array_map(fn() => rand(0, 9), range(0, 11));
        $d1 = $this->cnpjDigit($n, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $this->cnpjDigit(array_merge($n, [$d1]), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $base = implode('', $n);
        return substr($base, 0, 2) . '.' . substr($base, 2, 3) . '.' . substr($base, 5, 3) . '/0001-' . $d1 . $d2;
    }

    private function cnpjDigit(array $nums, array $weights): int
    {
        $sum = array_sum(array_map(fn($n, $w) => $n * $w, $nums, $weights));
        $rem = $sum % 11;
        return $rem < 2 ? 0 : 11 - $rem;
    }
}
