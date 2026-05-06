<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_days', function (Blueprint $table) {
            $table->json('tolerance_snapshot')->nullable()->after('is_closed');
        });
    }

    public function down(): void
    {
        Schema::table('work_days', function (Blueprint $table) {
            $table->dropColumn('tolerance_snapshot');
        });
    }
};
