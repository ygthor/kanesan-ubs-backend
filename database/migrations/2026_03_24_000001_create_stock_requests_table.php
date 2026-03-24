<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('stock_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_request_id');
            $table->string('item_no', 50);
            $table->string('description', 255)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('requested_qty', 15, 4)->default(0);
            $table->decimal('approved_qty', 15, 4)->nullable();
            $table->timestamps();

            $table->foreign('stock_request_id')->references('id')->on('stock_requests')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
    }
};
