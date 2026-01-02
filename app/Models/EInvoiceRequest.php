<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EInvoiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'customer_code',
        'order_id',
        'company_individual_name',
        'business_registration_number_old',
        'business_registration_number_new',
        'tin_number',
        'msic_code',
        'sales_service_tax_sst',
        'address',
        'person_in_charge',
        'contact',
        'email_address',
        'ic_number',
        'passport_number',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the order associated with this e-invoice request.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Get the customer associated with this e-invoice request.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_code', 'customer_code');
    }
}
