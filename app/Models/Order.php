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
        'branch_id',
        'customer_id',    // Foreign key to customers table
        'customer_code',  // Denormalized customer name for quick display
        'customer_name',  // Denormalized customer name for quick display
        'order_date',
        'status',         // e.g., 'pending', 'processing', 'completed', 'cancelled'
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
        'order_date' => 'datetime',
        'gross_amount' => 'decimal:2',
        'tax1' => 'decimal:2',
        'tax1_percentage' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the order.
     */
    public function customer()
    {
        // Assuming your Customer model is App\Models\Customer
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the items for the order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
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
     */
    public function calculate()
    {
        $total = 0;
        foreach ($this->items as $item) {
            // This assumes OrderItem model has an 'amount' accessor or calculates it
            $total += $item->amount; // You'll define getSubtotal() in OrderItem
        }
        $this->gross_amount = $total;

        $this->tax1 = $total * $this->tax1_percentage / 100;
        $this->grand_amount = $this->gross_amount + $this->tax1;
        $this->net_amount = $this->grand_amount - $this->discount;
        $this->updated_at = timestamp();
    }

    public function getReferenceNo()
    {
        $type = $this->type;
        $count = Order::where('type', "=", $type)->count();

        $found = false;
        $running_number = $count;
        while (!$found) {
            $refNo = $type . str_pad($running_number, 5, '0', STR_PAD_LEFT);
            $chk = Order::where('reference_no', "=", $refNo)->first();
            if ($chk == null) break;
            $running_number++;
        }

        return $refNo;
    }
}
