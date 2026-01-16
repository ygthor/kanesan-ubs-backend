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
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Period name/title');
            $table->date('start_date')->comment('Start date of the period');
            $table->date('end_date')->comment('End date of the period');
            $table->string('description')->nullable()->comment('Optional description');
            $table->boolean('is_active')->default(true)->comment('Whether the period is active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('periods');
    }
};
