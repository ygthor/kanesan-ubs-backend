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
        Schema::create('artrans_credit_note', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('invoice_id')->nullable()->comment('artrans_id for INV');
            $table->unsignedInteger('credit_note_id')->nullable()->comment('artrans_id for CN');
            $table->timestamp('created_at')->nullable()->useCurrent();
            
            // Add indexes for better query performance
            $table->index('invoice_id', 'idx_artrans_credit_note_invoice_id');
            $table->index('credit_note_id', 'idx_artrans_credit_note_credit_note_id');
            
            // Add foreign key constraints if needed (optional, depends on your requirements)
            // $table->foreign('invoice_id')->references('artrans_id')->on('artrans')->onDelete('cascade');
            // $table->foreign('credit_note_id')->references('artrans_id')->on('artrans')->onDelete('cascade');
            
            // Ensure one credit note can only be linked to one invoice (optional constraint)
            $table->unique('credit_note_id', 'uk_artrans_credit_note_credit_note_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('artrans_credit_note');
    }
};
