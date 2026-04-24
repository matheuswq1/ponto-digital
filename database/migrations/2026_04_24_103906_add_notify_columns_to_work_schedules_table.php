<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->boolean('notify_late')->default(true)->after('active');
            $table->boolean('notify_absence')->default(true)->after('notify_late');
            $table->boolean('notify_overtime')->default(true)->after('notify_absence');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn(['notify_late', 'notify_absence', 'notify_overtime']);
        });
    }
};
