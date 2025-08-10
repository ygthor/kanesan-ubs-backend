<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Artran extends BaseModel
{
    use HasFactory;

    protected $table = 'artrans';
    // If your primary key is not 'id', uncomment and set it.
    protected $primaryKey = 'REFNO'; 
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'CREATED_ON';
    const UPDATED_AT = 'UPDATED_ON';

    
    // Allow mass assignment for these fields
    protected $fillable = [
        'artrans_id',
        'TYPE',    // type
        'REFNO',
        'branch_id',
        'CUSTNO',    // Foreign key to customers table
        'DATE',  // Denormalized customer name for quick display
        'DESP',  // Denormalized customer name for quick display
        'TRANCODE',
        'GROSS_BILL',         // e.g., 'pending', 'processing', 'completed', 'cancelled'
        'NET_BIL',   
        'TAX1_BIL',   
        'GRAND_BIL',   
        'DEBIT_BIL',   
        'CREDIT_BIL',   
        'INVGROSS',   
        'NET',        
        'GRAND',        
        'DEBITAMT',        
        'CREDITAMT',        
        'CS_PM_WHT',        
        'NOTE',        
        'ISCASH',        
        'AGE',        
        'TERM',        
        'PLA_DODATE',        
        'NAME',        
        'TRDATETIME',        
        'USERID',        
        'BDATE',        
        'CREATED_BY',        
        'UPDATED_BY',        
        'CREATED_ON',        
        'UPDATED_ON',  
        // 'tax1_percentage' // Not a DB column, but useful for calculations
    ];

    // Cast fields to native types
    protected $casts = [
        'DATE' => 'datetime',
        'GROSS_BILL' => 'decimal:2',
        'NET_BIL' => 'decimal:2',
        'TAX1_BIL' => 'decimal:2',
        'GRAND_BIL' => 'decimal:2',
        'DEBIT_BIL' => 'decimal:2',
        'CREDIT_BIL' => 'decimal:2',
        'ISCASH' => 'boolean',
    ];

     public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set the visible property to be the fillable fields and the primary key
        $this->visible = [
            ...$this->fillable, // The spread operator unpacks the fillable array
            'items',
            'customer',
            // $this->getKeyName(), // The getKeyName() method gets the primary key column name (e.g., 'id')
        ];
    }

    /**
     * Get the customer that owns the invoice.
     * The local key is 'CUSTNO', the foreign key on the customers table is 'customer_code'.
     */
    public function customer()
    {
        // Adjust 'customer_code' to whatever the key is in your 'customers' table
        return $this->belongsTo(Customer::class, 'CUSTNO', 'customer_code');
    }

    /**
     * Get the items for the invoice.
     * The foreign key on 'artrans_items' is 'artrans_id' by convention.
     * If different, specify it: return $this->hasMany(ArTransItem::class, 'foreign_key_name');
     */
    public function items()
    {
        return $this->hasMany(ArTransItem::class, 'REFNO','REFNO');
    }

    /**
     * Calculate invoice totals based on its items.
     * Assumes 'tax1_percentage' and 'discount' are passed or set on the model.
     */
    public function calculate()
    {
        // Sum the 'AMT_BIL' from all related items
        $grossTotal = $this->items()->sum('AMT_BIL');
        $this->GROSS_BILL = $grossTotal;

        // Example tax calculation. You might get percentage from config or customer.
        $taxPercentage = $this->tax1_percentage ?? 6.00; // Default to 6% if not set
        $this->TAX1_BIL = $this->GROSS_BILL * ($taxPercentage / 100);

        $this->GRAND_BIL = $this->GROSS_BILL + $this->TAX1_BIL;

        // Net amount after a potential header-level discount
        $headerDiscount = $this->discount ?? 0.00;
        $this->NET_BIL = $this->GRAND_BIL - $headerDiscount;

        // For accounting: Invoices are typically debits
        $this->DEBIT_BIL = $this->NET_BIL;
        $this->CREDIT_BIL = 0;
    }

    /**
     * Generate a unique reference number (e.g., IV00001).
     */
    public function generateReferenceNumber()
    {
        // Use a transaction to prevent race conditions if possible
        $prefix = $this->TYPE;
        $lastInvoice = self::where('TYPE', $prefix)->orderBy('id', 'desc')->first();
        
        $number = 1;
        if ($lastInvoice && $lastInvoice->REFNO) {
            // Extract number part and increment
            $lastNumber = (int) substr($lastInvoice->REFNO, strlen($prefix));
            $number = $lastNumber + 1;
        }

        return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
}