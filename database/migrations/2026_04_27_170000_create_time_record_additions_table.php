<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_record_additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['entrada', 'saida'])->comment('Tipo do ponto a adicionar');
            $table->dateTime('datetime')->comment('Data/hora desejada para o ponto (UTC)');
            $table->text('justification')->comment('Justificativa obrigatória');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->foreignId('time_record_id')->nullable()->constrained()->onDelete('set null')
                  ->comment('TimeRecord criado após aprovação');
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_record_additions');
    }
};
