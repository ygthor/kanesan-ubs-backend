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
        Schema::table('customers', function (Blueprint $table) {
            // Add company_name2 column after company_name if it doesn't exist
            if (!Schema::hasColumn('customers', 'company_name2')) {
                $table->string('company_name2')->nullable()->after('company_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            // Drop the company_name2 column if it exists
            if (Schema::hasColumn('customers', 'company_name2')) {
                $table->dropColumn('company_name2');
            }
        });
    }
};

