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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();

            // Customer Information
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('customer_name')->nullable()->comment('Denormalized for convenience');
            $table->string('customer_code')->nullable()->comment('Denormalized for convenience');

            // Receipt Details
            $table->timestamp('receipt_date');
            $table->string('payment_type'); // e.g., Cash, Cheque, Online Transfer
            $table->decimal('debt_amount', 10, 2)->default(0.00);
            $table->decimal('transaction_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2);
            $table->string('payment_reference_no')->nullable(); // e.g., slip number, transaction ID

            // Cheque-specific details (nullable)
            $table->string('cheque_no')->nullable();
            $table->string('cheque_type')->nullable(); // e.g., Local, Outstation
            $table->string('bank_name')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Optional: for soft deleting receipts
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('receipts');
    }
};
