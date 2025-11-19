<?php

namespace Ssntpl\LaravelAcl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal model for role assignments
 * Used only for relationships, not for direct instantiation
 */
class RoleAssignment extends Model
{
    protected $table = 'acl_role_assignments';
    
    public $timestamps = true;
    
    protected $fillable = [
        'subject_type',
        'subject_id',
        'role_id',
        'resource_type',
        'resource_id',
        'expires_at',
    ];

    /**
     * Get the role this assignment belongs to
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the subject (user/model) that has this role
     */
    public function subject()
    {
        return $this->morphTo('subject');
    }

    /**
     * Get the resource this role applies to (optional)
     */
    public function resource()
    {
        return $this->morphTo('resource');
    }
}
