<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_item_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_received')->default(true);
            $table->string('received_item_name')->nullable();
            $table->decimal('received_quantity', 12, 2)->nullable();
            $table->string('received_unit')->nullable();
            $table->decimal('received_price', 15, 2)->nullable();
            $table->string('alternative_item_name')->nullable();
            $table->decimal('alternative_quantity', 12, 2)->nullable();
            $table->string('alternative_unit')->nullable();
            $table->decimal('alternative_price', 15, 2)->nullable();
            $table->text('alternative_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('purchase_order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_item_receipts');
    }
};
