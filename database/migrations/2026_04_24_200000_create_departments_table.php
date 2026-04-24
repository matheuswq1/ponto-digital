<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->time('entry_time')->nullable();
            $table->time('exit_time')->nullable();
            $table->unsignedSmallInteger('lunch_minutes')->default(60);
            $table->unsignedSmallInteger('tolerance_minutes')->default(10);
            $table->json('work_days')->nullable()->comment('0=dom..6=sab; padrão seg-sex');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
