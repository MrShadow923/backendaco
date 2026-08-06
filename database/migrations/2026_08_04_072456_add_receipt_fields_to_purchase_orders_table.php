<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->text('receipt_remarks')->nullable()->after('status');
            $table->timestamp('receipt_verified_at')->nullable()->after('receipt_remarks');
            $table->foreignId('receipt_verified_by')->nullable()->constrained('users')->nullOnDelete()->after('receipt_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['receipt_verified_by']);
            $table->dropColumn(['receipt_remarks', 'receipt_verified_at', 'receipt_verified_by']);
        });
    }
};
