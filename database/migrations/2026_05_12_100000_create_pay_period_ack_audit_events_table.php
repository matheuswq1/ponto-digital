<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_period_ack_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pay_period_acknowledgement_id');
            $table->enum('decision', ['approve', 'reject']);
            $table->char('snapshot_hash', 64);
            $table->longText('snapshot_json');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('client_meta')->nullable();
            $table->string('terms_version', 32)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('pay_period_acknowledgement_id', 'pp_ack_audit_ack_fk')
                ->references('id')->on('pay_period_acknowledgements')->cascadeOnDelete();

            $table->index(['pay_period_acknowledgement_id', 'recorded_at'], 'pp_ack_audit_ack_rec_ix');
            $table->index('snapshot_hash', 'pp_ack_audit_hash_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_period_ack_audit_events');
    }
};
