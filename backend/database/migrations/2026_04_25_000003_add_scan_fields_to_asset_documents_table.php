<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_documents', function (Blueprint $table) {
            $table->string('scan_status', 30)->default('clean')->after('file_size');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');
            $table->text('scan_error')->nullable()->after('scanned_at');

            $table->index(['tenant_id', 'scan_status']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_documents', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'scan_status']);
            $table->dropColumn(['scan_status', 'scanned_at', 'scan_error']);
        });
    }
};
