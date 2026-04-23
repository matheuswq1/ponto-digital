<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('entry_time')->nullable();
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->time('exit_time')->nullable();
            $table->integer('total_minutes')->default(0)->comment('Total de minutos trabalhados');
            $table->integer('expected_minutes')->default(0)->comment('Minutos esperados');
            $table->integer('extra_minutes')->default(0)->comment('Positivo = horas extras, negativo = falta');
            $table->integer('lunch_minutes')->default(0)->comment('Minutos de almoço');
            $table->enum('status', ['normal', 'falta', 'feriado', 'folga', 'afastamento'])->default('normal');
            $table->text('observations')->nullable();
            $table->boolean('is_closed')->default(false)->comment('Dia fechado/calculado');
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_days');
    }
};
