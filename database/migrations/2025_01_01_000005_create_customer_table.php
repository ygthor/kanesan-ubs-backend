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
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); // Primary key, auto-incrementing
            $table->string('customer_code')->unique()->comment('Unique code for the customer');
            $table->string('company_name');
            $table->string('address1');
            $table->string('address2')->nullable();
            $table->string('postcode', 10)->nullable(); // Adjusted length for postcode
            $table->string('state')->nullable();
            $table->string('territory')->nullable();
            $table->string('telephone1')->nullable();
            $table->string('telephone2')->nullable();
            $table->string('fax_no')->nullable();
            $table->string('contact_person')->nullable(); // Changed from contact1
            $table->string('customer_group')->nullable();
            $table->string('customer_type')->nullable();
            $table->string('lot_type')->nullable();

            // Optional: Add other common fields
            // $table->string('email')->nullable()->unique();
            // $table->boolean('is_active')->default(true);
            // $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            // $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps(); // Adds created_at and updated_at columns
            // $table->softDeletes(); // Optional: Adds deleted_at column for soft deletes
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customers');
    }
};