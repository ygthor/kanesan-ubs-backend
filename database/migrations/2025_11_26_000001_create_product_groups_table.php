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
        Schema::create('icgroup', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Product group name (e.g., JAYASAKI- MATA, BHAVANI, AKS)');
            $table->text('description')->nullable()->comment('Optional description for the product group');
            $table->string('CREATED_BY')->nullable();
            $table->string('UPDATED_BY')->nullable();
            $table->timestamp('CREATED_ON')->nullable();
            $table->timestamp('UPDATED_ON')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->unique('name', 'uk_icgroup_name');
            $table->index('CREATED_ON', 'idx_icgroup_created_on');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('icgroup');
    }
};

