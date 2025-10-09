<?php

namespace Ssntpl\Permissions\Traits;

use Ssntpl\Permissions\Models\ModelResourceRole;
use Ssntpl\Permissions\Models\Role;

trait HasRoles
{
    public function roles()
    {
        return Role::where('resource_type', get_class($this))->get();
    }

    public function modelRoles()
    {
        return $this->morphMany(ModelResourceRole::class, 'model');
    }

    public function role($resource = null)
    {
        if (config('permissions.role_on_resource')) {
            return $this->morphOne(ModelResourceRole::class, 'model')
                ->where('resource_id', $resource->id)
                ->where('resource_type', get_class($resource))
                ->with('role');
        } else {
            return $this->morphOne(ModelResourceRole::class, 'model')
                ->with('role');
        }
    }

    public function assignRole($role, $resource = null)
    {
        if (is_string($role)) {
            $role = Role::where('name', $role);
            $role = config('permissions.role_on_resource') ? $role->where('resource_type', get_class($resource))->first() : $role->first();
        }

        $currentRole = $this->role($resource);
        if ($currentRole->first()) {
            $currentRole = $currentRole->update(['role_id' => $role->id]);
        } else {
            if (config('permissions.role_on_resource')) {
                $currentRole = $this->modelRoles()->create([
                    'role_id' => $role->id,
                    'resource_id' => $resource->id,
                    'resource_type' => get_class($resource)
                ]);
            } else {
                $currentRole = $this->modelRoles()->create([
                    'role_id' => $role->id,
                ]);
            }
        }

        return $currentRole;
    }

    public function removeRole($resource = null)
    {
        return $this->role($resource)->delete();
    }

    public function hasRole($role, $resource = null): bool
    {
        $resource = $resource instanceof \Illuminate\Database\Eloquent\Collection 
            ? $resource->first()
            : $resource;

        if (is_string($role)) {
            $role = Role::where('name', $role);
            $role = config('permissions.role_on_resource') ? $role->where('resource_type', get_class($resource))->first() : $role->first();
        }
        if (!$role) {
            return false;
        }
        if ($this->role($resource)->first()?->role?->id == $role->id) {
            return true;
        }
        return false;
    }
   
    public function hasAnyRole($roles, $resource = null): bool
    {
        $roleNames = is_string($roles) ? explode('|', $roles) : (array) $roles;
        
        $resource = $resource instanceof \Illuminate\Database\Eloquent\Collection 
            ? $resource->first()
            : $resource;    
        
        foreach ($roleNames as $roleName) {
            if ($this->hasRole(trim($roleName), $resource)) {
                return true;
            }
        }
        
        return false;
    }

    public function getRoleName($resource = null)
    {
        return $this->role($resource)->first()?->role?->name ?? '';
    }
}
