<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Territory extends Model
{
    use HasFactory;

    protected $table = 'territories';

    protected $fillable = [
        'area',
        'description',
    ];

    /**
     * Get customers in this territory
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'territory', 'area');
    }
}
