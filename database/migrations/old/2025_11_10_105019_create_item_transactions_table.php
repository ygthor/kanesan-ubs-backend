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
        Schema::create('item_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('ITEMNO', 50)->comment('Item number from icitem table');
            $table->enum('transaction_type', ['in', 'out', 'adjustment'])->comment('Type of transaction: in (stock in), out (stock out), adjustment (manual adjustment)');
            $table->decimal('quantity', 10, 2)->comment('Quantity: positive for in, negative for out, can be positive/negative for adjustment');
            $table->string('reference_type', 50)->nullable()->comment('Type of reference: invoice, adjustment, purchase, etc.');
            $table->string('reference_id', 100)->nullable()->comment('Reference ID (e.g., invoice REFNO, adjustment id)');
            $table->text('notes')->nullable()->comment('Additional notes or remarks');
            $table->decimal('stock_before', 10, 2)->nullable()->comment('Stock quantity before this transaction');
            $table->decimal('stock_after', 10, 2)->nullable()->comment('Stock quantity after this transaction');
            $table->string('CREATED_BY')->nullable();
            $table->string('UPDATED_BY')->nullable();
            $table->timestamp('CREATED_ON')->nullable();
            $table->timestamp('UPDATED_ON')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('ITEMNO');
            $table->index('transaction_type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('CREATED_ON');
            
            // Foreign key constraint (if icitem table exists)
            // Note: Since icitem uses ITEMNO as primary key (string), we use a simple index
            // Foreign key constraint might not work if ITEMNO is not properly indexed in icitem
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_transactions');
    }
};
