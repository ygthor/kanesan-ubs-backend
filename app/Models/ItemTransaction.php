<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class ItemTransaction extends BaseModel
{
    use HasFactory;

    protected $table = 'item_transactions';
    const CREATED_AT = 'CREATED_ON';
    const UPDATED_AT = 'UPDATED_ON';

    protected $fillable = [
        'ITEMNO',
        'agent_no',
        'transaction_type',
        'quantity',
        'reference_type',
        'reference_id',
        'return_type', // 'good' or 'bad' for trade returns
        'notes',
        'stock_before',
        'stock_after',
        'CREATED_BY',
        'UPDATED_BY',
        'CREATED_ON',
        'UPDATED_ON',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_before' => 'decimal:2',
        'stock_after' => 'decimal:2',
        'CREATED_ON' => 'datetime',
        'UPDATED_ON' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->visible = [
            ...$this->fillable,
        ];
    }

    /**
     * Get the item (from icitem table) associated with this transaction.
     */
    public function item()
    {
        return $this->belongsTo(Icitem::class, 'ITEMNO', 'ITEMNO');
    }

    /**
     * Scope for stock in transactions
     */
    public function scopeStockIn($query)
    {
        return $query->where('transaction_type', 'in');
    }

    /**
     * Scope for stock out transactions
     */
    public function scopeStockOut($query)
    {
        return $query->where('transaction_type', 'out');
    }

    /**
     * Scope for adjustment transactions
     */
    public function scopeAdjustment($query)
    {
        return $query->where('transaction_type', 'adjustment');
    }

    /**
     * Scope for invoice sale transactions (stock out for invoices)
     */
    public function scopeInvoiceSale($query)
    {
        return $query->where('transaction_type', 'invoice_sale');
    }

    /**
     * Scope for invoice return transactions (stock return for credit notes)
     */
    public function scopeInvoiceReturn($query)
    {
        return $query->where('transaction_type', 'invoice_return');
    }

    /**
     * Scope for transactions by reference
     */
    public function scopeByReference($query, $referenceType, $referenceId)
    {
        return $query->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }
}
