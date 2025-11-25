<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

class Product extends BaseModel
{
    use HasFactory;
    
    protected $table = 'products';
    
    const CREATED_AT = 'CREATED_ON';
    const UPDATED_AT = 'UPDATED_ON';

    protected $fillable = [
        'code',
        'description',
        'group_name',
        'is_active',
        'CREATED_BY',
        'UPDATED_BY',
        'CREATED_ON',
        'UPDATED_ON',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'CREATED_ON' => 'datetime',
        'UPDATED_ON' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->visible = [
            ...$this->fillable,
        ];
    }

    /**
     * Scope to get only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by product group
     */
    public function scopeByGroup($query, $groupName)
    {
        return $query->where('group_name', $groupName);
    }
}
