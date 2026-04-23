<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['entrada', 'saida_almoco', 'volta_almoco', 'saida'])->comment('Tipo de batida');
            $table->dateTime('datetime')->comment('Data/hora da batida (UTC)');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable()->comment('Precisão GPS em metros');
            $table->string('photo_url')->nullable()->comment('URL da selfie no Firebase Storage');
            $table->string('ip_address', 45)->nullable();
            $table->text('device_info')->nullable()->comment('User-agent do dispositivo');
            $table->string('device_id')->nullable()->comment('ID único do dispositivo');
            $table->boolean('is_mock_location')->default(false)->comment('GPS mockado detectado');
            $table->boolean('offline')->default(false)->comment('Batida registrada offline');
            $table->timestamp('synced_at')->nullable()->comment('Quando sincronizou do offline');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->string('rejection_reason')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->unsignedBigInteger('original_record_id')->nullable()->comment('ID do registro original se for correção');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'datetime']);
            $table->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_records');
    }
};
