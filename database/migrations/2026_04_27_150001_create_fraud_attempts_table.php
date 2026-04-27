<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('rule_triggered', 50)
                  ->comment('mock_location | velocity_jump | wifi_mismatch | ip_city_mismatch');
            $table->json('details')->nullable()
                  ->comment('Dados brutos: ssid, ip, speed_kmh, city, etc.');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('device_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->enum('action_taken', ['blocked', 'warned', 'logged'])->default('logged');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['employee_id', 'created_at']);
            $table->index('rule_triggered');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_attempts');
    }
};
