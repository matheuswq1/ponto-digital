<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('employee_pay_period_acknowledgements');
        Schema::dropIfExists('pay_period_acknowledgements');

        Schema::create('pay_period_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pay_period_closure_id');
            $table->unsignedBigInteger('employee_id');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->text('employee_notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->foreign('pay_period_closure_id', 'pp_ack_closure_fk')
                ->references('id')->on('pay_period_closures')->cascadeOnDelete();
            $table->foreign('employee_id', 'pp_ack_emp_fk')
                ->references('id')->on('employees')->cascadeOnDelete();

            $table->unique(['pay_period_closure_id', 'employee_id'], 'pp_ack_closure_emp_uq');
            $table->index(['employee_id', 'status'], 'pp_ack_emp_st_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_period_acknowledgements');
    }
};
