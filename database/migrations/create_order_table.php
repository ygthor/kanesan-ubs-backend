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
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                // Assuming you have a 'customers' table with an 'id' primary key
                $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
                $table->string('customer_name')->nullable()->comment('Denormalized for convenience');
                $table->timestamp('order_date')->useCurrent();
                $table->string('status')->default('pending'); // e.g., pending, processing, shipped, delivered, cancelled
                $table->decimal('net_amount', 10, 2)->default(0.00);
                $table->text('remarks')->nullable();
                // Add other relevant fields: shipping_address_id, billing_address_id, payment_status, etc.
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
        Schema::dropIfExists('orders');
    }
};
