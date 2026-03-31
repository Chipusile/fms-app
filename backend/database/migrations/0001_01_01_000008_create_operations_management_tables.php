<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trip_number', 30);
            $table->text('purpose');
            $table->string('origin', 255);
            $table->string('destination', 255);
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            $table->unsignedBigInteger('start_odometer')->nullable();
            $table->unsignedBigInteger('end_odometer')->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->string('status', 30)->default('requested');
            $table->unsignedInteger('passengers')->nullable();
            $table->text('cargo_description')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'trip_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scheduled_start']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'driver_id']);
        });

        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number', 50)->nullable();
            $table->string('fuel_type', 30);
            $table->decimal('quantity_liters', 10, 2);
            $table->decimal('cost_per_liter', 12, 4);
            $table->decimal('total_cost', 14, 2);
            $table->unsignedBigInteger('odometer_reading');
            $table->boolean('is_full_tank')->default(true);
            $table->timestamp('fueled_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'fueled_at']);
            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'driver_id']);
            $table->index(['tenant_id', 'trip_id']);
        });

        Schema::create('odometer_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('reading');
            $table->string('source', 30);
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->timestamp('recorded_at');
            $table->text('notes')->nullable();
            $table->boolean('is_anomaly')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'recorded_at']);
            $table->index(['tenant_id', 'is_anomaly']);
            $table->index(['tenant_id', 'source', 'source_reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odometer_readings');
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('trips');
    }
};
