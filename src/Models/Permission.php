<?php

namespace Ssntpl\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Ssntpl\LaravelAcl\Models\Role;

class Permission extends Model
{
    protected $table = 'acl_permissions';

    protected $fillable = [
        'name',
        'resource_type'
    ];

    /**
     * Get all child permissions (implications)
     */
    public function children()
    {
        return $this->belongsToMany(
            Permission::class,
            'acl_permissions_implications',
            'parent_permission_id',
            'child_permission_id'
        );
    }

    /**
     * Get all parent permissions
     */
    public function parents()
    {
        return $this->belongsToMany(
            Permission::class,
            'acl_permissions_implications',
            'child_permission_id',
            'parent_permission_id'
        );
    }

    /**
     * Recursively get all implied permissions (descendants)
     * Returns collection with permission objects
     */
    public function getImpliedPermissions(): \Illuminate\Support\Collection
    {
        $cacheKey = "permission.{$this->id}.implied";
        
        return Cache::remember($cacheKey, config('acl.cache_ttl', 86400), function () {
            return $this->getAllNodes();
        });
    }

    /**
     * Recursively get all related permissions (children or parents) with cycle detection
     * 
     * @param bool $upward If true, traverse upward (parents), if false, traverse downward (children)
     * @param array|null &$visitedIds Array of permission IDs already visited (passed by reference)
     * @return \Illuminate\Support\Collection
     */
    private function getAllNodes(bool $upward = false, ?array &$visitedIds = null): \Illuminate\Support\Collection
    {
        // Initialize visitedIds if not provided
        if ($visitedIds === null) {
            $visitedIds = [];
        }
        
        // Prevent infinite loops and reprocessing by tracking visited permissions
        if (in_array($this->id, $visitedIds)) {
            return collect([$this]);
        }
        
        $visitedIds[] = $this->id;
        $nodes = collect([$this]);
        
        // Always pull fresh relations to avoid stale data while invalidating caches
        $directNodes = $upward ? $this->parents()->get() : $this->children()->get();
        
        foreach ($directNodes as $node) {
            $nodes = $nodes->merge($node->getAllNodes($upward, $visitedIds));
        }
        
        return $nodes->unique('id');
    }

    /**
     * Roles that have this permission directly assigned
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'acl_role_permissions',
            'permission_id',
            'role_id'
        )->withPivot('effect');
    }

    /**
     * Clear cache for this permission and all affected roles
     */
    public function clearCache(): void
    {
        Cache::forget("permission.{$this->id}.implied");
    }

    /**
     * Clear cache for all permissions and roles
     */
    public function clearRelatedCaches(): void
    {
        $affectedPermissions = $this->getAllNodes(true)->unique('id');

        $affectedPermissions->each(function (Permission $permission) {
            $permission->clearCache();
        });

        $permissionIds = $affectedPermissions->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        Role::whereHas('directPermissions', function ($query) use ($permissionIds) {
            $query->whereIn('acl_permissions.id', $permissionIds);
        })->get()->each->clearCache();
    }
}
