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
        if (!Schema::hasTable('invoice_orders')) {
            Schema::create('invoice_orders', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_refno', 255)->comment('References artrans.REFNO (invoice reference number)');
                $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
                $table->timestamps();
                
                // Ensure one invoice-order combination is unique (can't link same invoice to same order twice)
                $table->unique(['invoice_refno', 'order_id'], 'unique_invoice_order');
                
                // Indexes for faster lookups
                $table->index('invoice_refno', 'idx_invoice_refno');
                $table->index('order_id', 'idx_order_id');
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
        Schema::dropIfExists('invoice_orders');
    }
};
