<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptInvoice extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'receipt_invoices';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'receipt_id',
        'invoice_refno',
        'amount_applied',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount_applied' => 'decimal:2',
    ];

    /**
     * Get the receipt that this invoice link belongs to.
     */
    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }
}

