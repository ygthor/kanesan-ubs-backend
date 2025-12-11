<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ArTransItemDetail extends BaseModel
{
    use HasFactory;

    protected $table = 'artrans_items_detail';

    protected $fillable = [
        'artrans_item_id',
        'is_trade_return',
        'return_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_trade_return' => 'boolean',
    ];

    /**
     * Get the invoice item that this detail belongs to.
     */
    public function arTransItem()
    {
        return $this->belongsTo(ArTransItem::class, 'artrans_item_id', 'id');
    }
}

