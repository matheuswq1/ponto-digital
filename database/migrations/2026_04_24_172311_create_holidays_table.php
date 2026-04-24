<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->enum('scope', ['nacional', 'estadual', 'municipal'])->default('nacional');
            $table->string('state', 2)->nullable();   // UF (para estaduais)
            $table->string('city')->nullable();        // cidade (para municipais)
            $table->unsignedBigInteger('company_id')->nullable(); // null = todos
            $table->boolean('recurring')->default(true); // repete todo ano (usa mês+dia)
            $table->timestamps();

            $table->index(['date']);
            $table->index(['company_id', 'date']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
