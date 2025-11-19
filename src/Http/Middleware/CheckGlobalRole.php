<?php

namespace Ssntpl\LaravelAcl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckGlobalRole
{
    public function handle(Request $request, Closure $next, string $roles)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $roleNames = array_values(array_filter(array_map('trim', explode('|', $roles))));

        if (empty($roleNames)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        if (!method_exists($user, 'hasRole')) {
            throw new \RuntimeException('Authenticated user model must use the HasRoles trait to run check_global_role middleware.');
        }

        if (!$user->hasRole($roleNames)) {
            return response()->json(['message' => 'Insufficient permissions'], 403);
        }

        return $next($request);
    }
}
