<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'type',    // type
        'reference_no',
        'credit_invoice_no',  // Reference number of the invoice (INV) this credit note (CN) is linked to
        'branch_id',
        'customer_id',    // Foreign key to customers table
        'customer_code',  // Denormalized customer name for quick display
        'customer_name',  // Denormalized customer name for quick display
        'order_date',
        'status',         // e.g., 'pending', 'processing', 'completed', 'cancelled'
        'agent_no',       // Agent number (user's name)
        'gross_amount',   // Calculated total for the order
        'tax1',   // Calculated total for the order
        'tax1_percentage',   // Calculated total for the order
        'grand_amount',   // Calculated total for the order
        'net_amount',   // Calculated total for the order
        'discount',   // Calculated total for the order
        'remarks',        // Any additional remarks for the order
        'created_by',        // Any additional remarks for the order
        'updated_by',        // Any additional remarks for the order
        // Add other fields like 'shipping_address', 'billing_address', 'payment_method' etc.
    ];

    protected $casts = [
        'order_date' => 'datetime',  // Use 'datetime' to store full datetime with time
        'gross_amount' => 'decimal:2',
        'tax1' => 'decimal:2',
        'tax1_percentage' => 'decimal:2',
        'grand_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the order.
     * Uses customer_code to join with customers table.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_code', 'customer_code');
    }

    /**
     * Get the items for the order.
     * Uses reference_no to link orders and order_items.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'reference_no', 'reference_no');
    }

    /**
     * Accessor to get customer_name, using customer's name if company_name is empty
     */
    public function getCustomerNameAttribute($value)
    {
        // If customer_name is empty or 'N/A', try to get from customer relationship
        if (empty($value) || $value === 'N/A') {
            // Check if customer relationship is loaded to avoid N+1 queries
            if ($this->relationLoaded('customer') && $this->customer) {
                return $this->customer->company_name ?? $this->customer->name ?? 'N/A';
            }
            // If relationship not loaded, try to load it (but this could cause N+1)
            // Better to ensure customer is always loaded in queries
            if ($this->customer_id && !$this->relationLoaded('customer')) {
                $this->load('customer');
                if ($this->customer) {
                    return $this->customer->company_name ?? $this->customer->name ?? 'N/A';
                }
            }
        }
        return $value;
    }

    /**
     * Get the invoices that were created from this order.
     * Many-to-many relationship through invoice_orders pivot table.
     */
    public function invoices()
    {
        return $this->belongsToMany(Artran::class, 'invoice_orders', 'order_id', 'invoice_refno', 'id', 'REFNO');
    }

    /**
     * Calculate the total amount for the order based on its items.
     * This can be called before saving or used as an accessor.
     * Gross amount excludes trade returns (regular items only).
     */
    public function calculate()
    {
        $regularTotal = 0;
        $itemDiscounts = 0;

        // Trade returns are now in separate CN orders, so INV orders only have regular items
        // CN orders contain all trade return items
        foreach ($this->items as $item) {
            $lineAmount = $item->amount;
            $regularTotal += $lineAmount; // All items in this order are regular items
            $itemDiscounts += ($item->discount ?? 0);
        }

        // Gross amount is sum of all items (no trade returns to exclude)
        $this->gross_amount = $regularTotal;

        // Total discounts = item-level + order-level
        $orderDiscount = $this->discount ?? 0;
        $totalDiscount = $itemDiscounts + $orderDiscount;

        // Tax on gross
        $this->tax1 = $this->gross_amount * $this->tax1_percentage / 100;
        $this->grand_amount = $this->gross_amount + $this->tax1;

        // Net to pay mirrors _calculateNetToPay: gross - discounts
        $this->net_amount = $this->grand_amount - $totalDiscount;
        $this->updated_at = timestamp();
    }

    public function getReferenceNo()
    {
        $type = $this->type;
        // Use CNI prefix for CN type to prevent conflicts with existing numbering
        if ($type === 'INV') {
            $prefix = 'I';
        } elseif ($type === 'CN') {
            $prefix = 'CNI';
        } else {
            $prefix = $type;
        }
        
        $count = Order::where('type', "=", $type)->count();

        $found = false;
        $running_number = $count;
        while (!$found) {
            $refNo = $prefix . str_pad($running_number, 5, '0', STR_PAD_LEFT);
            $chk = Order::where('reference_no', "=", $refNo)->first();
            if ($chk == null) break;
            $running_number++;
        }

        return $refNo;
    }
}
