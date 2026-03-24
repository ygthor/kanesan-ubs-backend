<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
                $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null'); // Or restrict if product must exist

                $table->string('product_name')->comment('Denormalized product name at time of order');
                $table->string('sku_code')->comment('Denormalized SKU at time of order');

                $table->decimal('quantity', 8, 2); // Or integer
                $table->decimal('unit_price', 10, 2);
                $table->decimal('discount', 10, 2)->default(0.00);
                // $table->decimal('sub_total', 10, 2); // Calculated, usually not stored

                $table->boolean('is_free_good')->default(false);
                $table->boolean('is_trade_return')->default(false);
                $table->boolean('trade_return_is_good')->default(true)->comment('Only relevant if is_trade_return is true');

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_items');
    }
};
