<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get formatted created date safely.
     *
     * @param string $format
     * @return string
     */
    public function getFormattedCreatedAtAttribute($format = 'M d, Y')
    {
        return $this->created_at ? $this->created_at->format($format) : 'N/A';
    }

    /**
     * Get formatted updated date safely.
     *
     * @param string $format
     * @return string
     */
    public function getFormattedUpdatedAtAttribute($format = 'M d, Y')
    {
        return $this->updated_at ? $this->updated_at->format($format) : 'N/A';
    }

    /**
     * Get formatted email verified date safely.
     *
     * @param string $format
     * @return string
     */
    public function getFormattedEmailVerifiedAtAttribute($format = 'M d, Y')
    {
        return $this->email_verified_at ? $this->email_verified_at->format($format) : 'N/A';
    }

    /**
     * Get the roles that belong to the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id', 'id', 'role_id');
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole($role)
    {
        // KBS user has all roles
        if ($this->username === 'KBS' || $this->email === 'KBS@kanesan.my') {
            return true;
        }
        
        if (is_string($role)) {
            return $this->roles()->where('roles.role_id', $role)->exists();
        }
        
        return $this->roles()->where('roles.role_id', $role->role_id)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole($roles)
    {
        // KBS user has all roles
        if ($this->username === 'KBS' || $this->email === 'KBS@kanesan.my') {
            return true;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return $this->roles()->whereIn('roles.role_id', $roles)->exists();
    }

    /**
     * Check if the user has all of the given roles.
     */
    public function hasAllRoles($roles)
    {
        // KBS user has all roles
        if ($this->username === 'KBS' || $this->email === 'KBS@kanesan.my') {
            return true;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        $roleCount = $this->roles()->whereIn('roles.role_id', $roles)->count();
        return $roleCount === count($roles);
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission($permission)
    {
        // KBS user has all permissions
        if ($this->username === 'KBS' || $this->email === 'KBS@kanesan.my') {
            return true;
        }
        
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                if (is_string($permission)) {
                    $query->where('name', $permission);
                } else {
                    $query->where('id', $permission->id);
                }
            })
            ->exists();
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission($permissions)
    {
        // KBS user has all permissions
        if ($this->username === 'KBS' || $this->email === 'KBS@kanesan.my') {
            return true;
        }
        
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissions) {
                $query->whereIn('name', $permissions);
            })
            ->exists();
    }

    /**
     * Check if the user has all of the given permissions.
     */
    public function hasAllPermissions($permissions)
    {
        // KBS user has all permissions
        if ($this->username === 'KBS' || $this->email === 'KBS@kanesan.my') {
            return true;
        }
        
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        
        $permissionCount = $this->roles()
            ->whereHas('permissions', function ($query) use ($permissions) {
                $query->whereIn('name', $permissions);
            })
            ->count();
        
        return $permissionCount === count($permissions);
    }

    /**
     * Get all permissions for the user.
     */
    public function getAllPermissions()
    {
        // KBS user gets all permissions
        if ($this->username === 'KBS' || $this->email === 'KBS@kanesan.my') {
            return \App\Models\Permission::all();
        }
        
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }
}
