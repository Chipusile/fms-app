<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan_id', 50)->default('pro')->after('status');
            $table->timestamp('trial_ends_at')->nullable()->after('plan_id');
            $table->string('stripe_customer_id')->nullable()->after('trial_ends_at');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            $table->string('subscription_status', 50)->nullable()->after('stripe_subscription_id');

            $table->index('plan_id');
            $table->index('subscription_status');
            $table->index('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropIndex(['subscription_status']);
            $table->dropIndex(['trial_ends_at']);
            $table->dropColumn([
                'plan_id',
                'trial_ends_at',
                'stripe_customer_id',
                'stripe_subscription_id',
                'subscription_status',
            ]);
        });
    }
};
