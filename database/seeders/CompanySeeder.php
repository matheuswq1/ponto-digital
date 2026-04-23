<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(
            ['cnpj' => '00.000.000/0001-00'],
            [
                'name' => 'Empresa Demo Ltda',
                'email' => 'contato@empresa-demo.com.br',
                'phone' => '(11) 99999-9999',
                'address' => 'Rua das Flores, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zipcode' => '01310-100',
                'active' => true,
                'require_photo' => true,
                'require_geolocation' => false,
                'work_start' => '08:00:00',
                'work_end' => '18:00:00',
                'lunch_duration' => 60,
                'geofence_radius' => 500,
            ]
        );
    }
}
