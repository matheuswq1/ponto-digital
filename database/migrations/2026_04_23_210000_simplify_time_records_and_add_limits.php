<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Migrar registros antigos de almoço para o novo modelo antes de alterar ENUM
        // saida_almoco → saida (encerra o período)
        // volta_almoco → entrada (inicia novo período)
        DB::statement("UPDATE time_records SET type = 'saida' WHERE type = 'saida_almoco' AND deleted_at IS NULL");
        DB::statement("UPDATE time_records SET type = 'entrada' WHERE type = 'volta_almoco' AND deleted_at IS NULL");

        // 2. Alterar ENUM da tabela time_records
        DB::statement("ALTER TABLE time_records MODIFY COLUMN type ENUM('entrada','saida') NOT NULL");

        // 3. Adicionar limite de batidas diárias na tabela companies
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_daily_records')
                ->default(10)
                ->after('lunch_duration')
                ->comment('Máximo de batidas de ponto por dia por funcionário');
        });
    }

    public function down(): void
    {
        // Reverter ENUM (os dados de almoço não são recuperáveis, mas estrutura volta)
        DB::statement("ALTER TABLE time_records MODIFY COLUMN type ENUM('entrada','saida_almoco','volta_almoco','saida') NOT NULL");

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('max_daily_records');
        });
    }
};
