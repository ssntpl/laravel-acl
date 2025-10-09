<?php

namespace Ssntpl\Permissions\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Ssntpl\Permissions\Models\Role;

class RoleOrPermissionMiddleware
{
    public function handle(Request $request, Closure $next, $roleOrPermission, $resourceType = null)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $model = config('permissions.user_model', "App\\Models\\User");
        $model = $model::find($user->id);
        if (config('permissions.role_on_resource')) {
            $resourceId = $request->resource_id;
            if (!$resourceId) {
                return response()->json(['message' => 'Resource not found'], 404);
            }
            
            $resource = Role::findResource($resourceType, $resourceId);
            if (!$resource) {
                return response()->json(['message' => 'Resource not found'], 404);
            }
            
            $modelRole = $model->role($resource)->first();
        } else {
            $modelRole = $model->role()->first();
        }

        if (!$modelRole) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $items = explode('|', $roleOrPermission);
        
        foreach ($items as $item) {
            $item = trim($item);
            if ($model->hasRole($item, $resource) || $modelRole->role->permissions()->where('name', $item)->exists()) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Insufficient permissions'], 403);
    }
}
