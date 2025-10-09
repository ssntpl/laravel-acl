<?php

namespace Ssntpl\Permissions\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Role extends Model
{
    protected $fillable = [
        'name',
        'resource_type'
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, Grant::class)
                ->using(Grant::class)
                ->as('grant');
    }

    public function syncPermissions($permissions)
    {
        $permissionIds = $permissions?->map(fn ($permission) => $permission->getKey())->toArray();
        return $this->permissions()->sync($permissionIds);
    }

    public static function findResource($resourceType, $id)
    {
        if (! class_exists($resourceType)) {
            throw new InvalidArgumentException("Model class not found: {$resourceType}");
        }

        return $resourceType::find($id);
    }
}
