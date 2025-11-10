<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Icitem extends BaseModel
{
    use HasFactory;

    protected $table = 'icitem';
    // If your primary key is not 'id', uncomment and set it.
    protected $primaryKey = 'ITEMNO'; 
    public $incrementing = false;
    protected $keyType = 'string';
    
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'ITEMNO',
        'TYPE',    // type
        'CATEGORY',
        'GROUP',
        'DESP',
        'UNIT',    // Foreign key to customers table
        'UCOST',  // Denormalized customer name for quick display
        'PRICE',  // Denormalized customer name for quick display
        'UNIT2',  // Denormalized customer name for quick display
        'QTYBF',  // Denormalized customer name for quick display
        'FACTOR1',  // Denormalized customer name for quick display
        'FACTOR2',  // Denormalized customer name for quick display
        'PRICEU2',  // Denormalized customer name for quick display
        'T_UCOST',  // Denormalized customer name for quick display
        'QTY',  // Denormalized customer name for quick display

        'CREATED_BY',        
        'UPDATED_BY',        
        'CREATED_ON',        
        'UPDATED_ON',  
        // 'tax1_percentage' // Not a DB column, but useful for calculations
    ];

    // Cast fields to native types
    protected $casts = [
        
    ];

     public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set the visible property to be the fillable fields and the primary key
        $this->visible = [
            ...$this->fillable, // The spread operator unpacks the fillable array
            // $this->getKeyName(), // The getKeyName() method gets the primary key column name (e.g., 'id')
        ];
    }

    /**
     * Get all transactions for this item
     */
    public function transactions()
    {
        return $this->hasMany(ItemTransaction::class, 'ITEMNO', 'ITEMNO');
    }

    /**
     * Get current stock from transactions
     */
    public function getCurrentStockAttribute()
    {
        $total = $this->transactions()->sum('quantity');
        return $total !== null ? (float)$total : (float)($this->QTY ?? 0);
    }
}