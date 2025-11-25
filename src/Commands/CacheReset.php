<?php

namespace Ssntpl\LaravelAcl\Commands;

use Illuminate\Console\Command;
use Ssntpl\LaravelAcl\Models\Permission;
use Ssntpl\LaravelAcl\Models\Role;

class CacheReset extends Command
{
    protected $signature = 'acl:cache-reset';

    protected $description = 'Reset the permission cache';

    public function handle()
    {
        $this->info('Clearing permission cache...');
        
        // Clear all role permission caches
        Role::all()->each(function ($role) {
            $role->clearCache();
        });
        
        // Clear all permission children caches
        Permission::all()->each(function ($permission) {
            $permission->clearCache();
        });
        
        $this->info('Permission cache cleared successfully.');
        
        return Command::SUCCESS;
    }
}
