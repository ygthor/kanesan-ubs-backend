<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends BaseModel
{
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'unique_key',
        'reference_no',
        'order_id',
        'item_count',
        'product_id',
        'product_no',
        'product_name',         // Denormalized product name
        'description',
        'sku_code',             // Denormalized SKU
        'quantity',
        'unit_price',
        'discount',             // Discount amount for this line item
        'amount',
        'is_free_good',
        'is_trade_return',
        'trade_return_is_good', // Condition if it's a trade return
        'item_group',           // Item group type: Cash Sales, Invoice, etc.
        // 'sub_total', // Often calculated, not stored, or stored for historical pricing
    ];

    protected $casts = [
        'quantity' => 'decimal:2', // Or integer if you don't allow partial quantities
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'is_free_good' => 'boolean',
        'is_trade_return' => 'boolean',
        'trade_return_is_good' => 'boolean',
    ];

    /**
     * Get the order that this item belongs to.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product associated with the order item.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the master item (from icitem table) associated with this order item.
     * Uses product_no to match ITEMNO in icitem table.
     */
    public function item()
    {
        // Local key on 'order_items' is 'product_no'.
        // The Owner key (primary key) on 'icitem' is 'ITEMNO'.
        return $this->belongsTo(Icitem::class, 'product_no', 'ITEMNO');
    }

    /**
     * Accessor to get the unit from icitem
     */
    public function getUnitAttribute()
    {
        return $this->item ? $this->item->UNIT : null;
    }

    /**
     * Append unit to array/json output
     */
    protected $appends = ['unit'];

    /**
     * Hide the item relationship from default serialization to avoid recursion
     */
    protected $hidden = ['item'];

    public function calculate()
    {
        if ($this->is_free_good) {
            return 0.00;
        }
        $this->amount = $this->quantity * $this->unit_price;
    }
}
