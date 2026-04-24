<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hour_bank_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('requested_date')->comment('Data em que a folga será usufruída');
            $table->integer('minutes_requested')->comment('Minutos a debitar do banco');
            $table->text('justification');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('requested_date');
        });

        // Adiciona FK da tabela transactions → requests (criada antes desta)
        Schema::table('hour_bank_transactions', function (Blueprint $table) {
            $table->foreign('hour_bank_request_id')
                ->references('id')
                ->on('hour_bank_requests')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('hour_bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['hour_bank_request_id']);
        });
        Schema::dropIfExists('hour_bank_requests');
    }
};
