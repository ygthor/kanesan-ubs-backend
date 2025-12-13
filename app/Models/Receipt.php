<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'receipt_no',
        'customer_id',
        'customer_name',
        'customer_code',
        'receipt_date',
        'payment_type',
        'debt_amount',
        'trade_return_amount',
        'paid_amount',
        'cheque_no',
        'cheque_type',
        'cheque_date',
        'bank_name',
        'payment_reference_no',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'receipt_date' => 'datetime',  // Use 'datetime' to store full datetime with time
        'cheque_date' => 'date',   // Use 'date' for consistency
        'debt_amount' => 'decimal:2',
        'trade_return_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    /**
     * Get the customer that this receipt belongs to.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the invoice links for this receipt.
     */
    public function receiptInvoices()
    {
        return $this->hasMany(ReceiptInvoice::class);
    }
}
