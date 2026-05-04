<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('reference_month'); // 1-12
            $table->unsignedSmallInteger('reference_year');
            $table->string('file_url');
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size')->nullable()->comment('Bytes');
            $table->string('description', 120)->nullable()->comment('Ex: Holerite, 13º Salário');
            $table->boolean('notified')->default(false)->comment('Push enviado ao colaborador');
            $table->timestamps();

            $table->index(['employee_id', 'reference_year', 'reference_month']);
            $table->index(['company_id', 'reference_year', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
