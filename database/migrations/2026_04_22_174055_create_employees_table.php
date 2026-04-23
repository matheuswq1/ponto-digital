<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('cpf', 14)->unique();
            $table->string('cargo', 100);
            $table->string('department', 100)->nullable();
            $table->string('registration_number', 50)->nullable()->comment('Matrícula');
            $table->date('admission_date');
            $table->date('dismissal_date')->nullable();
            $table->enum('contract_type', ['clt', 'pj', 'estagio', 'temporario'])->default('clt');
            $table->integer('weekly_hours')->default(44)->comment('Horas semanais contratuais');
            $table->string('pis', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->string('photo_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'active']);
            $table->index('cpf');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
