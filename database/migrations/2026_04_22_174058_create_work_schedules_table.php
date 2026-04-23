<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('name', 100)->comment('Nome da jornada, ex: Padrão 8h');
            $table->time('entry_time')->default('08:00:00');
            $table->time('lunch_start')->default('12:00:00');
            $table->time('lunch_end')->default('13:00:00');
            $table->time('exit_time')->default('17:00:00');
            $table->integer('tolerance_minutes')->default(10)->comment('Tolerância de atraso em minutos');
            $table->json('work_days')->comment('Dias da semana: [1,2,3,4,5] = seg a sex');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
