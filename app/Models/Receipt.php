<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Receipt extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'receipt_no',
        'customer_id',
        'customer_name',
        'customer_code',
        'receipt_date',
        'payment_type',
        'debt_amount',
        'trade_return_amount',
        'paid_amount',
        'cheque_no',
        'cheque_type',
        'cheque_date',
        'bank_name',
        'payment_reference_no',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'receipt_date' => 'datetime',  // Use 'datetime' to store full datetime with time
        'cheque_date' => 'date',   // Use 'date' for consistency
        'debt_amount' => 'decimal:2',
        'trade_return_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    /**
     * Get the customer that this receipt belongs to.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the invoice links for this receipt.
     */
    public function receiptInvoices()
    {
        return $this->hasMany(ReceiptInvoice::class);
    }

    /**
     * Generate receipt number in format RS400001 (R prefix + agent prefix + running number)
     * For multiple payments to same invoice: RS400001, RS400001-1, RS400001-2, etc.
     * Links to invoice by extracting agent prefix from invoice refno
     * 
     * @param string|null $invoiceRefNo Optional invoice refno to extract agent prefix from
     * @param array|null $invoiceRefNos Optional array of invoice refnos (for multiple invoices, use first one)
     * @return string
     */
    public static function generateReceiptNo($invoiceRefNo = null, $invoiceRefNos = null)
    {
        // Use first invoice refno if array provided, otherwise use single refno
        $primaryInvoiceRefNo = $invoiceRefNo;
        if (!$primaryInvoiceRefNo && $invoiceRefNos && is_array($invoiceRefNos) && !empty($invoiceRefNos)) {
            $primaryInvoiceRefNo = $invoiceRefNos[0];
        }
        
        $prefix = 'R'; // Receipt prefix
        $baseReceiptNo = null;
        
        // Extract receipt number from invoice refno if provided
        // Invoice format: S400091 → Receipt: RS400091 (just add R prefix)
        // For multiple payments: RS400091, RS400091-1, RS400091-2, etc.
        if ($primaryInvoiceRefNo) {
            // Simply add R prefix to the invoice number
            // S400091 → RS400091
            $baseReceiptNo = 'R' . $primaryInvoiceRefNo;
        }
        
        // If we have a base receipt number (linked to invoice), check for existing receipts for same invoice
        if ($baseReceiptNo && $primaryInvoiceRefNo) {
            // Count how many receipts already exist for this invoice
            $existingReceiptCount = DB::table('receipt_orders')
                ->join('receipts', 'receipt_orders.receipt_id', '=', 'receipts.id')
                ->where('receipt_orders.order_refno', $primaryInvoiceRefNo)
                ->whereNull('receipts.deleted_at')
                ->count();
            
            if ($existingReceiptCount == 0) {
                // First receipt for this invoice: use base format RS100001
                // Check if this exact receipt number already exists (shouldn't happen, but safety check)
                $chk = self::where('receipt_no', '=', $baseReceiptNo)->first();
                if ($chk == null) {
                    return $baseReceiptNo;
                }
                // If exists, fall through to suffix format (this shouldn't normally happen)
            }
            
            // Multiple receipts for same invoice: use suffix format RS100001-1, RS100001-2, etc.
            // existingReceiptCount = 1 means 1 receipt exists, so next one should be -1 (second receipt)
            // existingReceiptCount = 2 means 2 receipts exist, so next one should be -2 (third receipt)
            $suffix = $existingReceiptCount; // Start suffix at 1 for second receipt
            $receiptNoWithSuffix = $baseReceiptNo . '-' . $suffix;
            
            // Ensure uniqueness (in case of gaps or deletions)
            $found = false;
            while (!$found) {
                $chk = self::where('receipt_no', '=', $receiptNoWithSuffix)->first();
                if ($chk == null) {
                    $found = true;
                    break;
                }
                $suffix++;
                $receiptNoWithSuffix = $baseReceiptNo . '-' . $suffix;
            }
            
            return $receiptNoWithSuffix;
        }
        
        // No invoice linked - use generic format with running number
        // Count existing receipts with same prefix to get running number
        $count = self::where('receipt_no', 'like', $prefix . '%')->count();
        
        $found = false;
        $running_number = $count + 1; // Start from 1
        while (!$found) {
            $receiptNo = $prefix . str_pad($running_number, 5, '0', STR_PAD_LEFT);
            $chk = self::where('receipt_no', '=', $receiptNo)->first();
            if ($chk == null) break;
            $running_number++;
        }
        
        return $receiptNo;
    }
}
