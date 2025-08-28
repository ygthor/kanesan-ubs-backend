<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    // Use existing table structure
    protected $primaryKey = 'role_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'role_id',
        'role_name'
    ];

    // Map to standard Laravel conventions
    public function getNameAttribute()
    {
        return $this->role_name;
    }

    public function setNameAttribute($value)
    {
        $this->role_name = $value;
    }

    /**
     * Get the users that belong to this role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id', 'role_id', 'id');
    }

    /**
     * Get the permissions that belong to this role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id', 'role_id', 'id');
    }

    /**
     * Check if the role has any of the given permissions.
     */
    public function hasAnyPermission($permissions)
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        
        return $this->permissions()->whereIn('name', $permissions)->exists();
    }

    /**
     * Check if the role has all of the given permissions.
     */
    public function hasAllPermissions($permissions)
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        
        $permissionCount = $this->permissions()->whereIn('name', $permissions)->count();
        return $permissionCount === count($permissions);
    }
}
