<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Código IBGE do município para buscar feriados regionais
            $table->string('ibge_code', 10)->nullable()->after('zipcode');
            // Data da última sincronização de feriados
            $table->timestamp('holidays_synced_at')->nullable()->after('ibge_code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['ibge_code', 'holidays_synced_at']);
        });
    }
};
