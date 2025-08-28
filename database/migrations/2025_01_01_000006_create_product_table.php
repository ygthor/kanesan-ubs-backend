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
        // Check if the table already exists to prevent errors on re-run
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id(); // Auto-incrementing primary key
                $table->string('name');
                $table->string('sku')->unique()->comment('Stock Keeping Unit');
                $table->decimal('price', 8, 2);
                $table->string('group_id_text')->nullable()->comment('Text identifier for group, e.g., g1');
                $table->string('sub_group_id_text')->nullable()->comment('Text identifier for sub-group, e.g., sg1_1');
                // Add other columns like 'description', 'image_url', 'stock_quantity', 'is_active' etc.
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
        Schema::dropIfExists('products');
    }
};
