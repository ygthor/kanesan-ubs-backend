<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $table = 'product';

    // Accessor
    public function getProductNameAttribute()
    {
        return $this->attributes['Product_English_Name'];
    }
    public function getProductNoAttribute()
    {
        return $this->attributes['Product_Id'];
    }
    public function getSkuAttribute()
    {
        return $this->attributes['Product_Id'];
    }

}
