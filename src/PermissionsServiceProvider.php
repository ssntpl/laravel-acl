<?php

namespace Ssntpl\Permissions;

use Illuminate\Support\ServiceProvider;
use Ssntpl\Permissions\Commands\CreatePermission;
use Ssntpl\Permissions\Commands\CreateRole;

class PermissionsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/permissions.php' => config_path('permissions.php'),
        ], 'permissions-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_roles_table.php' => database_path('migrations/2025_01_01_000001_create_role_table.php'),
        ], 'permissions-migrations');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/permissions.php', 'permissions');
        $this->commands([
            CreatePermission::class,
            CreateRole::class,
        ]);
    }
}
