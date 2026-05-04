<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->enum('type', ['info', 'aviso', 'urgente'])->default('info');
            $table->boolean('pinned')->default(false);
            $table->boolean('push_sent')->default(false)->comment('FCM push enviado');
            $table->timestamp('published_at')->nullable()->comment('Null = rascunho');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
