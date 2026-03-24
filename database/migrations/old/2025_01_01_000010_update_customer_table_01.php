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
            // Adding new fields based on the detailed Flutter form
            // Ensure the 'after' column exists if you use it, or place them as needed.
            // Defaulting to adding after existing columns or at the end.

            if (!Schema::hasColumn('customers', 'name')) {
                $table->string('name')->nullable()->after('customer_code'); // General name
            }
            if (!Schema::hasColumn('customers', 'email')) {
                $table->string('email')->nullable()->unique()->after('contact_person');
            }
            if (!Schema::hasColumn('customers', 'phone')) {
                $table->string('phone', 50)->nullable()->after('email'); // General phone
            }
             if (!Schema::hasColumn('customers', 'address')) {
                $table->text('address')->nullable()->after('fax_no'); // General address field
            }
            // Ensure address1 and address2 types are appropriate (string is usually fine)
            // If they were text before, keep them as text.

            if (!Schema::hasColumn('customers', 'segment')) {
                $table->string('segment')->nullable()->after('customer_type');
            }
            if (!Schema::hasColumn('customers', 'payment_type')) {
                $table->string('payment_type')->nullable()->after('segment');
            }
            if (!Schema::hasColumn('customers', 'payment_term')) {
                $table->string('payment_term')->nullable()->after('payment_type');
            }
            if (!Schema::hasColumn('customers', 'max_discount')) {
                // Storing as string for flexibility as per Flutter model,
                // or use decimal('max_discount', 8, 2)->nullable() for numeric.
                $table->string('max_discount')->nullable()->after('payment_term');
            }
            if (!Schema::hasColumn('customers', 'avatar_url')) {
                $table->string('avatar_url', 2048)->nullable()->after('lot_type'); // For URLs
            }

            // Modify existing columns if necessary (e.g., change type, make nullable)
            // Example: if 'company_name' was not nullable and now can be
            // if (Schema::hasColumn('customers', 'company_name')) {
            //     $table->string('company_name')->nullable()->change();
            // }
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
            // Drop the newly added columns if the migration is rolled back
            // It's good practice to list them explicitly.
            $columnsToDrop = [
                'name',
                'email',
                'phone',
                'address',
                'segment',
                'payment_type',
                'payment_term',
                'max_discount',
                'avatar_url',
            ];
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
