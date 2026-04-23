<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_record_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('edited_by')->constrained('users')->onDelete('cascade');
            $table->dateTime('original_datetime');
            $table->dateTime('new_datetime');
            $table->string('original_type', 50);
            $table->string('new_type', 50);
            $table->text('justification')->comment('Justificativa obrigatória');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();

            $table->index('time_record_id');
            $table->index('edited_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_record_edits');
    }
};
