<?php

namespace Ssntpl\LaravelAcl\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Ssntpl\LaravelAcl\Models\Role;
use Ssntpl\LaravelAcl\Models\RoleAssignment;

trait HasRoles
{
    /**
     * Get all role assignments for this model
     */
    public function roles(): MorphMany
    {
        return $this->morphMany(RoleAssignment::class, 'subject');
    }

    public function role($resource = null): MorphOne
    {
        $resourceId = null;
        $resourceType = null;
        if ($resource) {
            $resourceId = $resource->id;
            $resourceType = get_class($resource);
        }
        
        return $this->morphOne(RoleAssignment::class, 'subject')
            ->where('resource_id', $resourceId)
            ->where('resource_type', $resourceType)
            ->with('role');
    }

    public function getRole($resource = null): ?Role
    {
        $assignment = $this->roles()
            ->where('resource_id', optional($resource)->id)
            ->where('resource_type', $resource ? get_class($resource) : null)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('role')
            ->first();

        return $assignment?->role;
    }


    public function assignRole($role, $resource = null, $expiresAt = null)
    {
        if (is_numeric($role)) {
            $role = Role::find($role);
        } else {
            $role = Role::where('name', $role);
            $role = $resource ? $role->where('resource_type', get_class($resource))->first() : $role->first();
        }

        if (!$role) {
            throw new \InvalidArgumentException("Role not found");
        }

        $data = [
            'role_id' => $role->id,
        ];
        
        if ($expiresAt) {
            $data['expires_at'] = $expiresAt;
        }

        $currentRole = $this->role($resource)->first();
        
        if ($currentRole) {
            $currentRole->update($data);
            return $currentRole->fresh();
        } else {

            if ($resource) {
                $data['resource_id'] = $resource->id;
                $data['resource_type'] = get_class($resource);
            }

            return $this->roles()->create($data);
        }
    }

    public function removeRole($resource = null)
    {
        return $this->role($resource)->delete();
    }

    public function hasRole($roles, $resource = null): bool
    {
        $currentRole = $this->getRole($resource);

        if (!$currentRole) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];

        if (in_array($currentRole->id, $roles) || in_array($currentRole->name, $roles)) {
            return true;
        }

        return false;
    }
}
