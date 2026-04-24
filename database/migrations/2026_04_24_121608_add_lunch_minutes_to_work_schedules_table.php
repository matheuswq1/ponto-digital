<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            // Intervalo mínimo de almoço em minutos (opcional, livre para o colaborador)
            // null = sem intervalo fixo definido; o cálculo usa apenas os pares de ponto
            $table->unsignedSmallInteger('lunch_minutes')->nullable()->default(null)->after('lunch_end');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn('lunch_minutes');
        });
    }
};
