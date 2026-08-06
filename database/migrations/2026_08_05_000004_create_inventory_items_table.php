<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name')->unique();
            $table->string('display_name');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('unit');
            $table->decimal('latest_unit_price', 15, 2)->default(0);
            $table->decimal('average_unit_cost', 15, 2)->default(0);
            $table->timestamps();

            $table->index('item_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
