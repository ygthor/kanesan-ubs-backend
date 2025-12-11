<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use App\Casts\DateOnlyCast;

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
        'GROSS_BIL',         // Gross amount before discount and tax
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
        'AREA',
        'PLA_DODATE',
        'NAME',
        'EMAIL',
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
        // Use custom cast for TEXT DATE column to prevent timezone conversion
        // Since DATE is stored as TEXT (not DATE/DATETIME type), we need special handling
        'DATE' => DateOnlyCast::class,
        'GROSS_BIL' => 'decimal:2',
        'NET_BIL' => 'decimal:2',
        'TAX1_BIL' => 'decimal:2',
        'GRAND_BIL' => 'decimal:2',
        'DEBIT_BIL' => 'decimal:2',
        'CREDIT_BIL' => 'decimal:2',
        'ISCASH' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['invoice_refno', 'total_credit_notes_amount'];

    /**
     * Get the linked invoice REFNO for credit notes.
     * This is an accessor that will be included in JSON responses.
     */
    public function getInvoiceRefnoAttribute()
    {
        // Only for credit notes (CN/CR)
        if (!in_array($this->TYPE, ['CN', 'CR'])) {
            return null;
        }

        // Check if artrans_id is available
        if (!$this->artrans_id) {
            \Log::debug("invoice_refno accessor: artrans_id not available for CN {$this->REFNO}");
            return null;
        }

        try {
            // Query the linked invoice directly from the pivot table
            $creditNoteLink = \App\Models\ArtransCreditNote::where('credit_note_id', $this->artrans_id)->first();
            if ($creditNoteLink) {
                $linkedInvoice = self::where('artrans_id', $creditNoteLink->invoice_id)
                    ->where('TYPE', 'INV')
                    ->first();
                if ($linkedInvoice) {
                    \Log::debug("invoice_refno accessor: Found linked invoice {$linkedInvoice->REFNO} for CN {$this->REFNO}");
                    return $linkedInvoice->REFNO;
                } else {
                    \Log::debug("invoice_refno accessor: Link exists but invoice not found for CN {$this->REFNO}");
                }
            } else {
                \Log::debug("invoice_refno accessor: No link found for CN {$this->REFNO} (artrans_id: {$this->artrans_id})");
            }
        } catch (\Exception $e) {
            // Table might not exist yet, return null silently
            \Log::debug("invoice_refno accessor: Error for CN {$this->REFNO}: " . $e->getMessage());
            return null;
        }
        
        return null;
    }

    /**
     * Get the total amount of all linked credit notes for this invoice.
     * Only applicable for INV type invoices.
     * 
     * @return float
     */
    public function getTotalCreditNotesAmount()
    {
        // Only for invoices (INV)
        if ($this->TYPE !== 'INV') {
            return 0.0;
        }

        // Check if artrans_id is available
        if (!$this->artrans_id) {
            return 0.0;
        }

        try {
            $creditNotes = ArtransCreditNote::where('invoice_id', $this->artrans_id)
                ->with('creditNote')
                ->get();
            
            $total = 0.0;
            foreach ($creditNotes as $cnLink) {
                if ($cnLink->creditNote) {
                    $total += (float) ($cnLink->creditNote->NET_BIL ?? 0);
                }
            }
            
            return $total;
        } catch (\Exception $e) {
            \Log::debug("getTotalCreditNotesAmount: Error for INV {$this->REFNO}: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Accessor for total_credit_notes_amount (for JSON serialization).
     * 
     * @return float
     */
    public function getTotalCreditNotesAmountAttribute()
    {
        return $this->getTotalCreditNotesAmount();
    }

    /**
     * Get adjusted NET_BIL (original NET_BIL minus linked credit notes).
     * Only applicable for INV type invoices.
     * 
     * @return float
     */
    public function getNetBilAdjustedAttribute()
    {
        if ($this->TYPE !== 'INV') {
            return (float) $this->NET_BIL;
        }
        
        $totalCreditNotes = $this->getTotalCreditNotesAmount();
        return max(0, (float) $this->NET_BIL - $totalCreditNotes);
    }

    /**
     * Get adjusted GRAND_BIL (original GRAND_BIL minus linked credit notes).
     * Only applicable for INV type invoices.
     * 
     * @return float
     */
    public function getGrandBilAdjustedAttribute()
    {
        if ($this->TYPE !== 'INV') {
            return (float) $this->GRAND_BIL;
        }
        
        $totalCreditNotes = $this->getTotalCreditNotesAmount();
        return max(0, (float) $this->GRAND_BIL - $totalCreditNotes);
    }

    /**
     * Get adjusted GROSS_BIL (original GROSS_BIL minus linked credit notes).
     * Only applicable for INV type invoices.
     * 
     * @return float
     */
    public function getGrossBilAdjustedAttribute()
    {
        if ($this->TYPE !== 'INV') {
            return (float) $this->GROSS_BIL;
        }
        
        $totalCreditNotes = $this->getTotalCreditNotesAmount();
        return max(0, (float) $this->GROSS_BIL - $totalCreditNotes);
    }

    /**
     * Get adjusted TAX1_BIL (proportionally adjusted based on credit notes).
     * Only applicable for INV type invoices.
     * 
     * @return float
     */
    public function getTax1BilAdjustedAttribute()
    {
        if ($this->TYPE !== 'INV') {
            return (float) $this->TAX1_BIL;
        }
        
        $totalCreditNotes = $this->getTotalCreditNotesAmount();
        if ($totalCreditNotes == 0 || $this->GRAND_BIL == 0) {
            return (float) $this->TAX1_BIL;
        }
        
        // Proportionally adjust tax based on credit note ratio
        $creditNoteRatio = $totalCreditNotes / (float) $this->GRAND_BIL;
        return max(0, (float) $this->TAX1_BIL * (1 - $creditNoteRatio));
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set the visible property to be the fillable fields and the primary key
        $this->visible = [
            ...$this->fillable, // The spread operator unpacks the fillable array
            'items',
            'customer',
            'orders', // Include orders relationship
            'invoice_refno', // Include the accessor for credit notes
            'total_credit_notes_amount', // Total amount of linked credit notes
            'net_bil_adjusted', // Adjusted NET_BIL (deducting CN amounts)
            'grand_bil_adjusted', // Adjusted GRAND_BIL (deducting CN amounts)
            'gross_bil_adjusted', // Adjusted GROSS_BIL (deducting CN amounts)
            'tax1_bil_adjusted', // Adjusted TAX1_BIL (proportionally adjusted)
            'original_amount', // Original NET_BIL before adjustments
            'credit_note_amount', // Total credit note amount
            'net_amount', // Net amount after credit notes
            'paid_amount', // Total payments made
            'outstanding_amount', // Outstanding balance
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
        return $this->hasMany(ArTransItem::class, 'REFNO', 'REFNO');
    }

    /**
     * Get the orders that this invoice was created from.
     * Many-to-many relationship through invoice_orders pivot table.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'invoice_orders', 'invoice_refno', 'order_id', 'REFNO', 'id');
    }

    /**
     * Get credit notes linked to this invoice (when this is an INV).
     * One invoice can have multiple credit notes.
     */
    public function creditNotes()
    {
        return $this->hasMany(ArtransCreditNote::class, 'invoice_id', 'artrans_id')
            ->with('creditNote');
    }

    /**
     * Get the invoice that this credit note is linked to (when this is a CN).
     * One credit note belongs to one invoice.
     */
    public function linkedInvoice()
    {
        return $this->hasOne(ArtransCreditNote::class, 'credit_note_id', 'artrans_id')
            ->with('invoice');
    }

    /**
     * Get the invoice directly (convenience method).
     */
    public function invoice()
    {
        return $this->hasOneThrough(
            Artran::class,
            ArtransCreditNote::class,
            'credit_note_id', // Foreign key on artrans_credit_note
            'artrans_id',     // Foreign key on artrans (invoice)
            'artrans_id',     // Local key on artrans (this credit note)
            'invoice_id'      // Local key on artrans_credit_note
        )->where('artrans.TYPE', 'INV');
    }

    /**
     * Calculate invoice totals based on its items.
     * Assumes 'tax1_percentage' and 'discount' are passed or set on the model.
     */
    public function calculate()
    {
        // Sum the 'AMT_BIL' from all related items
        $grossTotal = $this->items()->sum('AMT_BIL');
        
        // Set GROSS_BIL (gross amount before discount and tax)
        $this->GROSS_BIL = $grossTotal;
        
        // Set INVGROSS (invoice gross - typically same as GROSS_BIL)
        $this->INVGROSS = $grossTotal;

        // Example tax calculation. You might get percentage from config or customer.
        $taxPercentage = $this->tax1_percentage ?? 0.00; // Default to 0% if not set
        $this->TAX1_BIL = $grossTotal * ($taxPercentage / 100);

        $this->GRAND_BIL = $grossTotal + $this->TAX1_BIL;

        // Net amount after a potential header-level discount
        $headerDiscount = $this->discount ?? 0.00;
        $this->NET_BIL = $this->GRAND_BIL - $headerDiscount;

        // For accounting: Invoices are typically debits
        $this->CREDIT_BIL = 0;
        $this->DEBIT_BIL = 0;
        if( in_array($this->TYPE,['INV','SO','IV','CS','CB'])){
            $this->DEBIT_BIL = $this->NET_BIL;
        }
        if( in_array($this->TYPE,['CN']) ){
            $this->CREDIT_BIL = $this->NET_BIL;
        }
    }

    /**
     * Generate a unique reference number (e.g., IV00001).
     */
    public static function generateReferenceNumber($type)
    {
        // Use a transaction to prevent race conditions if possible
        $prefix = $type;
        $lastInvoice = self::where('TYPE', $prefix)->orderBy('REFNO', 'desc')->first();

        $number = 1;
        if ($lastInvoice && $lastInvoice->REFNO) {
            // Extract number part and increment
            $lastNumber = (int) substr($lastInvoice->REFNO, strlen($prefix));
            $number = $lastNumber + 1;
        }

        return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    public function getDocumentTitleAttribute()
    {
        switch ($this->TYPE) {
            case 'INV':
                return 'Invoice';
            case 'CN':
            case 'CR': // Handling both 'Credit Note' and 'Credit Return'
                return 'Credit Note';
            case 'CS':
            case 'CB': // Handling both 'Cash Sale' and 'Cash Bill'
                return 'Cash Bill';
            default:
                return 'Document'; // A fallback for any other types
        }
    }

    /**
     * Override date serialization to return date-only format (YYYY-MM-DD)
     * This prevents timezone conversion issues when retrieving dates from the database
     * and ensures dates are always returned as date-only strings in API responses
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        // Format as date-only to prevent timezone conversion issues
        // Use the date's timezone, not UTC
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }
        return $date->format('Y-m-d');
    }
}
