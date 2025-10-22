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
}
