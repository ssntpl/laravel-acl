<?php

namespace Ssntpl\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class Role extends Model
{
    protected $table = 'acl_roles';
    
    public $timestamps = false;

    protected $fillable = [
        'name',
        'resource_type'
    ];

    /**
     * Direct permissions relationship with effect field
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'acl_role_permissions',
            'role_id',
            'permission_id'
        )->withPivot('effect');
    }

    /**
     * Get effective (allowed) permissions (cached).
     */
    public function permissions()
    {
        $cacheKey = "role.{$this->id}.permissions";

        return Cache::remember($cacheKey, config('acl.cache_ttl', 86400), function () {
            $allowed = collect();
            $directPermissions = $this->directPermissions()->get();
            $deniedIds = $directPermissions
                ->where(fn ($permission) => $permission->pivot->effect === 'DENY')
                ->pluck('id')
                ->values();

            $directPermissions->each(function (Permission $permission) use ($allowed) {
                if ($permission->pivot->effect === 'DENY') {
                    return;
                }

                $allowed->push($permission);

                $permission->getImpliedPermissions()->each(function (Permission $child) use ($allowed) {
                    $allowed->push($child);
                });
            });

            return $allowed
                ->unique('id')
                ->reject(fn (Permission $permission) => $deniedIds->contains($permission->id))
                ->values();
        });
    }

    /**
     * Get denied permissions (direct DENY assignments only).
     */
    public function deniedPermissions()
    {
        return $this->directPermissions()
            ->wherePivot('effect', 'DENY')
            ->get();
    }

    /**
     * Check if a permission is allowed
     */
    public function hasPermission($permission): bool
    {
        $permissionId = $permission instanceof Permission ? $permission->id : null;
        $permissionName = is_string($permission) ? $permission : null;
        
        if ($permissionId) {
            return $this->permissions()->contains('id', $permissionId);
        }
        
        if ($permissionName) {
            return $this->permissions()->contains('name', $permissionName);
        }
        
        return false;
    }

    /**
     * Check if a permission is denied
     */
    public function hasDeniedPermission($permission): bool
    {
        $permissionId = $permission instanceof Permission ? $permission->id : null;
        $permissionName = is_string($permission) ? $permission : null;
        
        if ($permissionId) {
            return $this->deniedPermissions()->contains('id', $permissionId);
        }
        
        if ($permissionName) {
            return $this->deniedPermissions()->contains('name', $permissionName);
        }
        
        return false;
    }

    /**
     * Check if permission is effectively allowed (not denied and in allowed list)
     */
    public function can($permission): bool
    {
        // First check if explicitly denied
        if ($this->hasDeniedPermission($permission)) {
            return false;
        }
        
        // Then check if explicitly allowed
        return $this->hasPermission($permission);
    }

    public function syncPermissions($permissions)
    {
        $permissionIds = $permissions?->map(fn ($permission) => $permission->getKey())->toArray();
        $result = $this->directPermissions()->sync($permissionIds);
        
        // Clear cache when permissions change
        $this->clearCache();
        
        return $result;
    }

    /**
     * Sync permissions with effect
     */
    public function syncPermissionsWithEffect(array $permissionsWithEffect): array
    {
        // Format: ['permission_id' => 'ALLOW'|'DENY', ...]
        $syncData = [];
        foreach ($permissionsWithEffect as $permissionId => $effect) {
            $syncData[$permissionId] = ['effect' => $effect];
        }
        
        $result = $this->directPermissions()->sync($syncData);
        
        // Clear cache when permissions change
        $this->clearCache();
        
        return $result;
    }

    /**
     * Clear all cached permissions for this role
     */
    public function clearCache(): void
    {
        Cache::forget("role.{$this->id}.permissions");
    }

    /**
     * Resolve a role from a mixed value (ID, name, Role instance, or RoleAssignment instance)
     * 
     * @param mixed $role Can be a Role instance, RoleAssignment instance, role ID (int/string), or role name (string)
     * @return Role|null Returns the Role instance or null if not found
     * @throws InvalidArgumentException If the role parameter is invalid
     */
    public static function resolve($role, $resourceType = null): ?Role
    {
        // If it's null, return null
        if ($role === null) {
            return null;
        }

        // If it's a RoleAssignment instance, extract the role from it
        if ($role instanceof RoleAssignment) {
            return $role->role;
        }

        // If it's already a Role instance, refresh it from DB to ensure it's current
        if ($role instanceof self) {
            // If the model has an ID (exists in DB), refresh it
            if ($role->exists && $role->getKey()) {
                try {
                    $role->refresh();
                    return $role;
                } catch (ModelNotFoundException $e) {
                    // Model was deleted from DB, return null
                    return null;
                }
            }
            
            // Model doesn't have an ID, check if it exists in DB by name and resource_type
            // (since there's a unique constraint on ['name', 'resource_type'])
            if ($role->name) {
                return self::where('name', $role->name)
                    ->where('resource_type', $role->resource_type ?? $resourceType)
                    ->first();
            }
            
            return null;
        }

        // If it's numeric (ID), find by ID
        if (is_numeric($role)) {
            return self::find($role);
        }

        // If it's a string, try to find by name
        if (is_string($role)) {
            return self::where('name', $role)->where('resource_type', $resourceType)->first();
        }

        // Invalid type
        throw new InvalidArgumentException(
            'Role must be a Role instance, RoleAssignment instance, role ID (int/string), or role name (string). Got: ' . gettype($role)
        );
    }
    
}
