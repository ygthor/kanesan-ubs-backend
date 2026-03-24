<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequestItem extends Model
{
    protected $fillable = [
        'stock_request_id',
        'item_no',
        'description',
        'unit',
        'requested_qty',
        'approved_qty',
    ];

    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class);
    }
}
