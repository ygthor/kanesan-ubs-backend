<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include new invoice transaction types
        DB::statement("ALTER TABLE `item_transactions` MODIFY COLUMN `transaction_type` ENUM('in', 'out', 'adjustment', 'invoice_sale', 'invoice_return') NOT NULL COMMENT 'Type of transaction: in (stock in), out (stock out), adjustment (manual adjustment), invoice_sale (stock out for invoice), invoice_return (stock return for credit note/refund)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        // First, update any invoice_sale or invoice_return records to 'out' or 'in'
        DB::statement("UPDATE `item_transactions` SET `transaction_type` = 'out' WHERE `transaction_type` = 'invoice_sale'");
        DB::statement("UPDATE `item_transactions` SET `transaction_type` = 'in' WHERE `transaction_type` = 'invoice_return'");
        
        // Then modify the enum back
        DB::statement("ALTER TABLE `item_transactions` MODIFY COLUMN `transaction_type` ENUM('in', 'out', 'adjustment') NOT NULL COMMENT 'Type of transaction: in (stock in), out (stock out), adjustment (manual adjustment)'");
    }
};
