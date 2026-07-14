<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->json('entry_time_by_day')->nullable()->after('exit_time');
            $table->json('exit_time_by_day')->nullable()->after('entry_time_by_day');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['entry_time_by_day', 'exit_time_by_day']);
        });
    }
};
