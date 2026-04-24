<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        // Feriados nacionais brasileiros recorrentes (mês/dia fixo)
        $nacionais = [
            ['01-01', 'Confraternização Universal'],
            ['04-21', 'Tiradentes'],
            ['05-01', 'Dia do Trabalho'],
            ['09-07', 'Independência do Brasil'],
            ['10-12', 'Nossa Senhora Aparecida'],
            ['11-02', 'Finados'],
            ['11-15', 'Proclamação da República'],
            ['11-20', 'Consciência Negra'],
            ['12-25', 'Natal'],
        ];

        foreach ($nacionais as [$md, $nome]) {
            Holiday::updateOrCreate(
                ['date' => "2000-{$md}", 'scope' => 'nacional', 'company_id' => null],
                ['name' => $nome, 'recurring' => true]
            );
        }

        // Feriados móveis 2026 (Carnaval / Corpus Christi / Sexta da Paixão)
        $moveis2026 = [
            ['2026-02-16', 'Segunda-feira de Carnaval'],
            ['2026-02-17', 'Terça-feira de Carnaval'],
            ['2026-04-03', 'Sexta-feira Santa'],
            ['2026-06-04', 'Corpus Christi'],
        ];

        foreach ($moveis2026 as [$data, $nome]) {
            Holiday::updateOrCreate(
                ['date' => $data, 'scope' => 'nacional', 'company_id' => null],
                ['name' => $nome, 'recurring' => false]
            );
        }
    }
}
