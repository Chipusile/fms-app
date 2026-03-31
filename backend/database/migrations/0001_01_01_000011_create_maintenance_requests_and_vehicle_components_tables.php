<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_number', 40);
            $table->string('title', 160);
            $table->string('request_type', 50);
            $table->string('priority', 30)->default('medium');
            $table->string('status', 30)->default('submitted');
            $table->date('needed_by')->nullable();
            $table->timestamp('requested_at');
            $table->unsignedInteger('odometer_reading')->nullable();
            $table->text('description');
            $table->text('review_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'request_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'requested_by']);
            $table->index(['tenant_id', 'needed_by']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('maintenance_request_id')
                ->nullable()
                ->after('maintenance_schedule_id')
                ->constrained('maintenance_requests')
                ->nullOnDelete();

            $table->index(['tenant_id', 'maintenance_request_id']);
        });

        Schema::create('vehicle_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('component_number', 40);
            $table->string('component_type', 50);
            $table->string('position_code', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->string('condition_status', 30)->default('good');
            $table->date('installed_at')->nullable();
            $table->unsignedInteger('installed_odometer')->nullable();
            $table->unsignedInteger('expected_life_days')->nullable();
            $table->unsignedInteger('expected_life_km')->nullable();
            $table->unsignedInteger('reminder_days')->nullable();
            $table->unsignedInteger('reminder_km')->nullable();
            $table->date('next_replacement_at')->nullable();
            $table->unsignedInteger('next_replacement_km')->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->date('last_inspected_at')->nullable();
            $table->date('removed_at')->nullable();
            $table->unsignedInteger('removed_odometer')->nullable();
            $table->text('removal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'component_number']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'component_type']);
            $table->index(['tenant_id', 'next_replacement_at']);
            $table->index(['tenant_id', 'next_replacement_km']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_components');

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'maintenance_request_id']);
            $table->dropConstrainedForeignId('maintenance_request_id');
        });

        Schema::dropIfExists('maintenance_requests');
    }
};
