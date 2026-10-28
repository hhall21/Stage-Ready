<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('billing_exempt')->default(false);
        });
        Schema::create('coach_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('checkout_session_id')->nullable();
            $table->timestamp('checkout_expires_at')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('last_paid_at')->nullable();
            $table->timestamp('paid_through')->nullable();
            $table->timestamp('retention_expires_at')->nullable()->index();
            $table->timestamp('data_purged_at')->nullable();
            $table->unsignedBigInteger('last_status_event_at')->default(0);
            $table->timestamps();
        });
        Schema::create('coach_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membership_id')->constrained('coach_memberships');
            $table->string('stripe_invoice_id')->unique();
            $table->unsignedInteger('amount_pence');
            $table->string('currency', 3);
            $table->timestamp('paid_at');
            $table->timestamp('period_started_at');
            $table->timestamp('period_ended_at');
            $table->timestamps();
        });
        Schema::create('stripe_webhook_events', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('type');
            $table->timestamp('handled_at');
        });
        Schema::table('coaching_handovers', function (Blueprint $table): void {
            $table->boolean('delete_after_view')->default(false);
            $table->string('view_token_hash', 64)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('billing_period_ended_at')->nullable();
            $table->unique(['client_id', 'coach_id', 'billing_period_ended_at'], 'handovers_billing_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('coaching_handovers', function (Blueprint $table): void {
            $table->dropUnique('handovers_billing_period_unique');
            $table->dropColumn(['delete_after_view', 'view_token_hash', 'opened_at', 'billing_period_ended_at']);
        });
        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('coach_payments');
        Schema::dropIfExists('coach_memberships');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('billing_exempt'));
    }
};
