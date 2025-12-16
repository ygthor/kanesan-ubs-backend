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
     * Uses reference_no to link order_items and orders.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'reference_no', 'reference_no');
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
    protected $appends = ['unit', 'linked_credit_note_count'];

    /**
     * Hide the item relationship from default serialization to avoid recursion
     */
    protected $hidden = ['item'];

    /**
     * Product name fallback: prefer stored product_name, otherwise use icitem NAME.
     */
    public function getProductNameAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }

        // When item relation is eager loaded (with items.item), use its NAME
        if ($this->relationLoaded('item') && $this->item) {
            return $this->item->NAME ?? null;
        }

        // Lazy load as a final fallback (should rarely happen because item is eager loaded)
        return $this->item ? $this->item->NAME : null;
    }

    /**
     * Get the count of linked credit note items that reference this order item.
     * This counts how many credit note items (from CN orders linked to the invoice)
     * have the same product_no as this order item.
     */
    public function getLinkedCreditNoteCountAttribute()
    {
        // Only calculate for items that belong to INV orders
        $order = $this->order;
        if (!$order || $order->type !== 'INV') {
            return 0;
        }

        // Get all CN orders linked to this invoice
        $linkedCNOrders = Order::where('credit_invoice_no', $order->reference_no)
            ->where('type', 'CN')
            ->get();

        if ($linkedCNOrders->isEmpty()) {
            return 0;
        }

        // Count credit note items with the same product_no
        $count = 0;
        foreach ($linkedCNOrders as $cnOrder) {
            $count += OrderItem::where('reference_no', $cnOrder->reference_no)
                ->where('product_no', $this->product_no)
                ->count();
        }

        return $count;
    }

    public function calculate()
    {
        if ($this->is_free_good) {
            return 0.00;
        }
        $this->amount = $this->quantity * $this->unit_price;
    }
}
