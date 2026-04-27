<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('block_mock_location')->default(false)->after('require_geolocation')
                  ->comment('Bloquear GPS falso (mock location)');
            $table->boolean('block_velocity_jump')->default(false)->after('block_mock_location')
                  ->comment('Bloquear salto impossível de localização');
            $table->integer('velocity_jump_threshold_kmh')->default(300)->after('block_velocity_jump')
                  ->comment('Velocidade máxima permitida entre dois pontos (km/h)');
            $table->boolean('require_wifi')->default(false)->after('velocity_jump_threshold_kmh')
                  ->comment('Exigir rede Wi-Fi específica');
            $table->json('allowed_wifi_ssids')->nullable()->after('require_wifi')
                  ->comment('Lista de SSIDs autorizados (array JSON)');
            $table->boolean('block_unknown_ip_city')->default(false)->after('allowed_wifi_ssids')
                  ->comment('Alertar/bloquear se cidade do IP divergir da empresa');
            $table->enum('fraud_action', ['warn', 'block'])->default('warn')->after('block_unknown_ip_city')
                  ->comment('warn = apenas registar; block = bloquear o ponto');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'block_mock_location',
                'block_velocity_jump',
                'velocity_jump_threshold_kmh',
                'require_wifi',
                'allowed_wifi_ssids',
                'block_unknown_ip_city',
                'fraud_action',
            ]);
        });
    }
};
