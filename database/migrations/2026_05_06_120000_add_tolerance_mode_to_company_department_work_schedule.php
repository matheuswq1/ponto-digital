<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tolerance_mode', 32)->default('daily_dead_band')->after('max_daily_records');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('tolerance_mode', 32)->nullable()->after('tolerance_minutes');
        });

        Schema::table('work_schedules', function (Blueprint $table) {
            $table->string('tolerance_mode', 32)->nullable()->after('tolerance_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('tolerance_mode');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('tolerance_mode');
        });

        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn('tolerance_mode');
        });
    }
};
