<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Optional: if you want soft deletes

class Customer extends Model
{
    use HasFactory;
    // use SoftDeletes; // Optional: Uncomment if you want to enable soft deletes

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_code',
        'name',             // General name for the customer (individual or company)
        'company_name',     // Company name line 1
        'company_name2',    // Company name line 2
        'contact_person',
        'email',
        'phone',            // Primary general phone number
        'telephone1',       // Can be office phone
        'telephone2',       // Can be mobile or alternative
        'fax_no',
        'address',          // General full address (e.g., for display)
        'address1',         // Street address line 1
        'address2',         // Street address line 2
        'address3',         // Street address line 3
        'address4',         // Auto-generated: postcode + state
        'postcode',
        'state',
        'territory',
        'customer_group',
        'customer_type',    // From dropdown/text input
        'segment',          // From dropdown/text input
        'payment_type',     // From dropdown/text input
        'payment_term',
        'max_discount',     // Consider casting to float/decimal if it's numeric
        'lot_type',
        'avatar_url',       // URL to the customer's avatar/logo
        'agent_no',         // Agent number/name (mirrors assigned user)
        // 'is_active',     // Example: if you add an active status
        // 'created_by',
        // 'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // 'max_discount' => 'decimal:2', // Example if max_discount is numeric
        // 'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    // protected $hidden = [];

    /**
     * Default values for attributes.
     *
     * @var array
     */
    // protected $attributes = [
    //     'is_active' => true,
    // ];

    // Relationships (examples, uncomment and modify if needed)
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the invoices (artrans) for this customer.
     * The foreign key on 'artrans' is 'CUSTNO', the local key is 'customer_code'.
     */
    public function artrans()
    {
        return $this->hasMany(Artran::class, 'CUSTNO', 'customer_code');
    }

    /**
     * Get invoices with explicit collation handling
     */
    public function invoices()
    {
        return $this->hasMany(Artran::class, 'CUSTNO', 'customer_code')
            ->where('TYPE', 'INV');
    }

    /**
     * Get the users assigned to this customer.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_customers', 'customer_id', 'user_id')
            ->withPivot('created_at', 'updated_at')
            ->withTimestamps();
    }

    /**
     * Get the user-customer assignments for this customer.
     */
    public function userCustomers()
    {
        return $this->hasMany(UserCustomer::class);
    }



    public static function fromCode($cpde){
        return self::where('customer_code',$cpde)->first();

    }

    /**
     * Boot the model.
     * Auto-generate address4 from postcode and state, and name from company_name2 when creating or updating.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate fields before saving (create or update)
        static::saving(function ($customer) {
            $customer->generateAddress4();
            $customer->generateName();
        });
    }

    /**
     * Generate address4 from postcode and state.
     * Format: "postcode state" (e.g., "12345 Selangor")
     */
    public function generateAddress4()
    {
        $parts = [];
        
        if (!empty($this->postcode)) {
            $parts[] = trim($this->postcode);
        }
        
        if (!empty($this->state)) {
            $parts[] = trim($this->state);
        }
        
        $this->address4 = !empty($parts) ? implode(' ', $parts) : null;
    }

    /**
     * Generate name from company_name2.
     * Sets name to company_name2 if company_name2 is provided.
     */
    public function generateName()
    {
        if (!empty($this->company_name2)) {
            $this->name = trim($this->company_name2);
        }
    }
}
