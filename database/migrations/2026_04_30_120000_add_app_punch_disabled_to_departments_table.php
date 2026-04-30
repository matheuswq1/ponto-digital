<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('app_punch_disabled')
                ->default(false)
                ->after('active')
                ->comment('Quando true, colaboradores deste departamento só podem bater ponto pelo totem');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('app_punch_disabled');
        });
    }
};
