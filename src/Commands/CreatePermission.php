<?php

namespace Ssntpl\LaravelAcl\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Ssntpl\LaravelAcl\Models\Permission;
use Ssntpl\LaravelAcl\Models\Role;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;

class CreatePermission extends Command implements PromptsForMissingInput
{
    protected $signature = 'acl:create-permission  
        {permission : The ID or name of the permission update or create}
        {resource_type? : The resource type of the permission (e.g. App\\Models\\Team) - only used when creating}
        {--implied= : Comma-separated list of implied permission names or ids}';

    protected $description = 'Create or update a permission (only implied permissions can be updated)';

    public function handle()
    {
        $permissionInput = $this->argument('permission');
        $resourceType = $this->argument('resource_type');
        
        // Convert empty string to null for resource_type
        $resourceType = empty($resourceType) ? null : $resourceType;

        try {
            // Check if input is numeric (ID) or string (name)
            $isId = is_numeric($permissionInput);
            
            if ($isId) {
                // ID provided - find by ID
                $permission = Permission::find($permissionInput);
                
                if (!$permission) {
                    $this->error("Permission with ID `{$permissionInput}` not found.");
                    return Command::FAILURE;
                }
                
                $this->info("Found existing permission: `{$permission->name}` (ID: {$permission->id})." . ($resourceType ? " Resource type: `{$resourceType}` will be ignored." : ''));
                $isUpdate = true;
            } else {
                // Name provided - find or create by name
                $permission = Permission::where('name', $permissionInput)->first();
                
                if ($permission) {
                    $this->info("Found existing permission: `{$permission->name}` (ID: {$permission->id})." . ($resourceType ? " Resource type: `{$resourceType}` will be ignored." : ''));
                    $isUpdate = true;
                } else {
                    // Create new permission
                    $permission = Permission::create([
                        'name' => $permissionInput,
                        'resource_type' => $resourceType
                    ]);
                    $this->info("Created permission: `{$permission->name}` (ID: {$permission->id}) " . ($resourceType ? " with resource type: `{$resourceType}`." : ''));
                    $isUpdate = false;
                }
            }

            // Handle implied permissions
            $impliedPermissions = $this->getImpliedPermissions();
            
            if (!empty($impliedPermissions)) {
                $this->attachImpliedPermissions($permission, $impliedPermissions);
                // Clear caches after attaching implied permissions
                $permission->clearCache();
                
                if ($isUpdate) {
                    $this->newLine();
                    $this->info("Permission `{$permission->name}` updated successfully.");
                }
            } else {
                if ($isUpdate) {
                    $this->info("No implied permissions specified. Permission `{$permission->name}` unchanged.");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->newLine();
            $this->info("Permission operation failed.");
            return Command::FAILURE;
        }
    }

    /**
     * Get implied permissions from option or prompt
     */
    protected function getImpliedPermissions(): array
    {
        $impliedOption = $this->option('implied');
        
        if ($impliedOption) {
            return array_map('trim', explode(',', $impliedOption));
        }

        // If not provided via option, prompt the user
        if ($this->interactive() && confirm(
            label: 'Do you want to add implied permissions?',
            default: false
        )) {
            $impliedInput = text(
                label: 'Enter implied permission names (comma-separated)',
                placeholder: 'project.read, project.update',
                default: '',
                required: false,
            );

            if (!empty($impliedInput)) {
                return array_map('trim', explode(',', $impliedInput));
            }
        }

        return [];
    }

    /**
     * Attach implied permissions (child permissions) to the permission
     */
    protected function attachImpliedPermissions(Permission $permission, array $impliedPermissionNames): void
    {
        $childPermissions = [];

        foreach ($impliedPermissionNames as $impliedName) {
            if (empty($impliedName)) {
                continue;
            }

            try {
                $childPermission = Permission::firstOrCreate(
                    ['name' => trim($impliedName)],
                    ['name' => trim($impliedName)]
                );

                $childPermissions[] = $childPermission->id;

                if ($childPermission->wasRecentlyCreated) {
                    $this->info("  → Created implied permission: `{$childPermission->name}`");
                } else {
                    $this->info("  → Using existing implied permission: `{$childPermission->name}`");
                }
            } catch (\Exception $e) {
                $this->warn("  → Failed to create implied permission `{$impliedName}`: {$e->getMessage()}");
            }
        }

        if (!empty($childPermissions)) {
            // Attach child permissions (avoid duplicates)
            $existingChildren = $permission->children()->pluck('id')->toArray();
            $newChildren = array_diff($childPermissions, $existingChildren);
            
            if (!empty($newChildren)) {
                $permission->children()->attach($newChildren);
                $this->info("  → Attached " . count($newChildren) . " implied permission(s) to `{$permission->name}`");
            } else {
                $this->info("  → All implied permissions were already attached to `{$permission->name}`");
            }
        }
    }

    protected function promptForMissingArgumentsUsing()
    {
        return [
            'permission' => fn () => text(
                label: 'Enter permission ID or name',
                placeholder: '1 or "create"',
                default: '',
                required: true,
            ),
            'resource_type' => fn () => text(
                label: 'Enter resource type (optional, only used when creating)',
                placeholder: 'App\\Models\\Team',
                default: '',
                required: false,
            ),
        ];
    }
}
