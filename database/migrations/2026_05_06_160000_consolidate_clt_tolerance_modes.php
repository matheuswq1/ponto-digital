<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Um único modo CLT ativo: bucket progressivo + almoço por duração (efeito jornada).
 * Valores legados são convertidos para {@see WorkToleranceResolver::MODE_CLT_EVENT}.
 */
return new class extends Migration
{
    private const LEGACY = ['clt_event_based', 'clt_event_strict', 'clt_event_progressive_cap'];

    private const TARGET = 'clt_event_progressive_duration';

    public function up(): void
    {
        foreach (['companies', 'departments', 'work_schedules'] as $table) {
            foreach (self::LEGACY as $from) {
                DB::table($table)->where('tolerance_mode', $from)->update(['tolerance_mode' => self::TARGET]);
            }
        }
    }

    public function down(): void
    {
        // Irreversível: não há como recuperar qual modo legado cada linha tinha.
    }
};
