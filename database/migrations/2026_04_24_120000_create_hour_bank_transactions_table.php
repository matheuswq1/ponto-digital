<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hour_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('work_day_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('hour_bank_request_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['extra', 'deficit', 'folga_aprovada', 'ajuste_manual']);
            $table->integer('minutes')->comment('Positivo = crédito, negativo = débito');
            $table->string('description')->nullable();
            $table->date('reference_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['employee_id', 'reference_date']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hour_bank_transactions');
    }
};
