<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_period_acknowledgements', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->after('responded_at');
            $table->index(['employee_id', 'superseded_at'], 'pp_ack_emp_sup_ix');
        });

        Schema::table('pay_period_closures', function (Blueprint $table) {
            $table->foreignId('corrected_from_closure_id')
                ->nullable()
                ->after('closed_by')
                ->constrained('pay_period_closures')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pay_period_closures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrected_from_closure_id');
        });

        Schema::table('pay_period_acknowledgements', function (Blueprint $table) {
            $table->dropIndex('pp_ack_emp_sup_ix');
            $table->dropColumn('superseded_at');
        });
    }
};
