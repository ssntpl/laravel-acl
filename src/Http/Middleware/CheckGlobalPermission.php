<?php

namespace Ssntpl\LaravelAcl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckGlobalPermission
{
    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!method_exists($user, 'getRole')) {
            throw new \RuntimeException('Authenticated user model must use the HasRoles trait to run check_global_permission middleware.');
        }

        $role = $user->getRole();

        if (!$role) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        $permissionNames = array_values(array_filter(array_map('trim', explode('|', $permissions))));

        foreach ($permissionNames as $permissionName) {
            if ($role->can($permissionName)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Insufficient permissions'], 403);
    }
}
