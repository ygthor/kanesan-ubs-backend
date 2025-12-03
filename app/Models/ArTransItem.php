<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ArTransItem extends BaseModel
{
    use HasFactory;

    protected $table = 'artrans_items';
    const CREATED_AT = 'CREATED_ON';
    const UPDATED_AT = 'UPDATED_ON';

    protected $fillable = [
        'id',
        'unique_key',
        'artrans_id',
        'TYPE',
        'REFNO',
        'TRANCODE',
        'ITEMNO',
        'CUSTNO',
        'FPERIOD',         // Denormalized product name
        'DATE',
        'ITEMCOUNT',             // Denormalized SKU
        'DESP',
        'LOCATION',
        'SIGN',             // Discount amount for this line item
        'QTY_BIL',
        'PRICE_BIL',
        'UNIT_BIL',
        'AMT1_BIL', // Condition if it's a trade return
        'AMT_BIL', // Condition if it's a trade return
        'QTY', // Condition if it's a trade return
        'PRICE', // Condition if it's a trade return
        'UNIT', // Condition if it's a trade return
        'DODATE', // Condition if it's a trade return
        'SODATE', // Condition if it's a trade return
        'SODATE', // Condition if it's a trade return
        'NAME', // Condition if it's a trade return
        'TRDATETIME', // Condition if it's a trade return
        'CREATED_BY',
        'UPDATED_BY',
        'CREATED_ON',
        'UPDATED_ON',
        // Add boolean flags if you have columns for them
        // 'is_free_good', 'is_trade_return'
    ];

    protected $casts = [
        'QTY' => 'decimal:2',
        'PRICE' => 'decimal:2',
        'AMT_BIL' => 'decimal:2',
        'DATE' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set the visible property to be the fillable fields and the primary key
        $this->visible = [
            ...$this->fillable, // The spread operator unpacks the fillable array
            'item',
            'detail'
        ];
    }

    /**
     * Get the invoice header (Artran) that this item belongs to.
     */
    public function artran()
    {
        return $this->belongsTo(Artran::class, 'REFNO','REFNO');
    }

    /**
     * Get the product associated with the order item.
     * The local key is 'TRANCODE', the foreign key on products is 'product_no'.
     */
    /**
     * Get the master item (from icitem table) associated with this invoice line.
     */
    public function item()
    {
        // Local key on 'artrans_items' is 'TRANCODE'.
        // The Owner key (primary key) on 'icitem' is 'ITEMNO'.
        return $this->belongsTo(Icitem::class, 'ITEMNO', 'ITEMNO');
    }

    /**
     * Get the detail record for this invoice item (trade return information).
     */
    public function detail()
    {
        return $this->hasOne(ArTransItemDetail::class, 'artrans_item_id', 'id');
    }

    /**
     * Calculate the total amount for this line item.
     */
    public function calculate()
    {
        // SIGN determines if it's a sale (1) or return (-1)
        // Set sign based on type, e.g., SO is sale, CR is credit/return
        $this->SIGN = in_array($this->TYPE, ['CR', 'RT']) ? -1 : 1;

        $lineTotal = $this->QTY * $this->PRICE;

        // Apply SIGN to the final amount.
        // AMT_BIL stores the final calculated amount for this line.
        $this->AMT_BIL = $lineTotal * $this->SIGN;

        // You can use other fields for more complex logic
        // For example, AMT1_BIL could be the gross amount before line-item discounts
        $this->AMT1_BIL = $lineTotal;
        $this->QTY_BIL = $this->QTY;
        $this->PRICE_BIL = $this->PRICE;
    }
}
