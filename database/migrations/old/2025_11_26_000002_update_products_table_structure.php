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
        // Drop existing products table if it exists (old structure)
        Schema::dropIfExists('products');
        
        // Create new products table with new structure
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->comment('Product code (e.g., A100, A1K, PBBT)');
            $table->text('description')->nullable()->comment('Product description');
            $table->string('group_name', 255)->comment('Product group name (e.g., JAYASAKI- MATA, BHAVANI, AKS) - stored directly for UBS compatibility');
            $table->boolean('is_active')->default(true)->comment('Whether the product is active');
            $table->string('CREATED_BY')->nullable();
            $table->string('UPDATED_BY')->nullable();
            $table->timestamp('CREATED_ON')->nullable();
            $table->timestamp('UPDATED_ON')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->unique('code', 'uk_products_code');
            $table->index('group_name', 'idx_products_group_name');
            $table->index('code', 'idx_products_code');
            $table->index('is_active', 'idx_products_active');
            $table->index('CREATED_ON', 'idx_products_created_on');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
        
        // Optionally recreate old structure if needed
        // This is left empty as the old structure should be documented separately
    }
};

