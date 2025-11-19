<?php

namespace Ssntpl\LaravelAcl;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ssntpl\LaravelAcl\Models\Role;
use Ssntpl\LaravelAcl\Models\Permission;

class AclServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCommands();

        if ($this->app->runningInConsole()) {
            $this->registerConsoleCommands();
            $this->registerPublishing();
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        $this->registerCacheInvalidation();
    }
    
    /**
     * Register cache invalidation events
     */
    protected function registerCacheInvalidation(): void
    {
        Permission::saved(function (Permission $permission) {
            $permission->clearRelatedCaches();
        });

        Permission::deleted(function (Permission $permission) {
            $permission->clearRelatedCaches();
        });

        Role::saved(function (Role $role) {
            $role->clearCache();
        });

        Role::deleted(function (Role $role) {
            $role->clearCache();
        });

        Event::listen('eloquent.saved: *', function ($model) {
            if ($model instanceof Pivot) {
                $this->handlePivotChange($model);
            }
        });

        Event::listen('eloquent.deleted: *', function ($model) {
            if ($model instanceof Pivot) {
                $this->handlePivotChange($model);
            }
        });
    }

    /**
     * Clear caches for models touched via pivot tables.
     */
    protected function handlePivotChange(Pivot $pivot): void
    {
        $table = $pivot->getTable();

        if ($table === 'acl_permissions_implications') {
            $this->clearPermissionsByIds([
                $pivot->parent_permission_id ?? $pivot->getAttribute('parent_permission_id'),
                $pivot->child_permission_id ?? $pivot->getAttribute('child_permission_id'),
            ]);
        }

        if ($table === 'acl_role_permissions') {
            $this->clearPermissionsByIds([
                $pivot->permission_id ?? $pivot->getAttribute('permission_id'),
            ]);

            $this->clearRolesByIds([
                $pivot->role_id ?? $pivot->getAttribute('role_id'),
            ]);
        }
    }

    protected function clearPermissionsByIds(array $ids): void
    {
        $ids = array_values(array_filter(array_unique($ids)));

        if (empty($ids)) {
            return;
        }

        Permission::whereIn('id', $ids)
            ->get()
            ->each
            ->clearRelatedCaches();
    }

    protected function clearRolesByIds(array $ids): void
    {
        $ids = array_values(array_filter(array_unique($ids)));

        if (empty($ids)) {
            return;
        }

        Role::whereIn('id', $ids)
            ->get()
            ->each
            ->clearCache();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/acl.php',
            'acl'
        );
    }

    /**
     * Commands that must always be registered (used programmatically).
     */
    protected function registerCommands(): void
    {
        $this->commands([
            Commands\CacheReset::class,
        ]);
    }

    /**
     * Commands that are only useful when running Artisan manually.
     */
    protected function registerConsoleCommands(): void
    {
        $this->commands([
            Commands\AssignRole::class,
            Commands\CreatePermission::class,
            Commands\CreateRole::class,
        ]);
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/acl.php' => config_path('acl.php'),
        ], 'acl-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/2025_01_02_000101_create_acl_table.php' =>
            database_path('migrations/2025_01_02_000101_create_acl_table.php'),
        ], 'acl-migrations');
    }
}
