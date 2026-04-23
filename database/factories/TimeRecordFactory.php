<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\TimeRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeRecord>
 */
class TimeRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => 'entrada',
            'datetime' => Carbon::now()->subHours(rand(1, 8)),
            'latitude' => fake()->latitude(-23.7, -23.4),
            'longitude' => fake()->longitude(-46.8, -46.3),
            'accuracy' => fake()->randomFloat(2, 3, 50),
            'ip_address' => fake()->ipv4(),
            'device_info' => 'Mozilla/5.0 (Android 12; Mobile) AppleWebKit/537.36',
            'device_id' => fake()->uuid(),
            'is_mock_location' => false,
            'offline' => false,
            'status' => 'pendente',
        ];
    }

    public function entrada(): static
    {
        return $this->state(fn() => ['type' => 'entrada']);
    }

    public function saida(): static
    {
        return $this->state(fn() => ['type' => 'saida']);
    }

    public function offline(): static
    {
        return $this->state(fn() => [
            'offline' => true,
            'synced_at' => now(),
        ]);
    }

    public function forDate(string $date, string $time): static
    {
        return $this->state(fn() => [
            'datetime' => Carbon::parse("{$date} {$time}"),
        ]);
    }
}
