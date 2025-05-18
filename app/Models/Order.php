<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',    // Foreign key to customers table
        'customer_name',  // Denormalized customer name for quick display
        'order_date',
        'status',         // e.g., 'pending', 'processing', 'completed', 'cancelled'
        'total_amount',   // Calculated total for the order
        'remarks',        // Any additional remarks for the order
        // Add other fields like 'shipping_address', 'billing_address', 'payment_method' etc.
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
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
     * Calculate the total amount for the order based on its items.
     * This can be called before saving or used as an accessor.
     */
    public function calculateTotalAmount()
    {
        $total = 0;
        foreach ($this->items as $item) {
            // This assumes OrderItem model has an 'amount' accessor or calculates it
            $total += $item->getSubtotal(); // You'll define getSubtotal() in OrderItem
        }
        $this->total_amount = $total;
        return $total;
    }
}
