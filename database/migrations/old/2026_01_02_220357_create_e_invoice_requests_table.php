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
        Schema::create('e_invoice_requests', function (Blueprint $table) {
            $table->id();
            
            // Invoice information
            $table->string('invoice_no')->nullable()->index();
            $table->string('customer_code')->nullable()->index();
            $table->string('order_id')->nullable()->index();
            
            // Section 2 - User input fields
            $table->string('company_individual_name')->nullable();
            $table->string('business_registration_number_old')->nullable();
            $table->string('business_registration_number_new')->nullable();
            $table->string('tin_number')->nullable();
            $table->string('msic_code')->nullable();
            $table->string('sales_service_tax_sst')->nullable();
            $table->text('address')->nullable();
            $table->string('person_in_charge')->nullable();
            $table->string('contact')->nullable();
            $table->string('email_address')->nullable();
            $table->string('ic_number')->nullable();
            $table->string('passport_number')->nullable();
            
            // Additional metadata
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_invoice_requests');
    }
};
