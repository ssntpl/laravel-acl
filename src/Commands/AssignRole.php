<?php

namespace Ssntpl\LaravelAcl\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ssntpl\LaravelAcl\Models\Role;

class AssignRole extends Command
{
    protected $signature = 'acl:assign-role
        {role : The id or name of the role. e.g. 1 or "admin"}
        {subject : The subject to assign the role to. eg. App\\\\Models\\\\User:1}
        {resource? : The resource to assign the role to. eg. App\\\\Models\\\\Project:1 or leave out for global}
        {--expires-at= : Optional expiration datetime for the role assignment. Format: Y-m-d H:i:s or any valid datetime string}';

    protected $description = 'Assign a role to a subject on a resource.';

    public function handle()
    {
        $roleInput = $this->argument('role');
        $subjectInput = $this->argument('subject');
        $resourceInput = $this->argument('resource');
        $expiresAtInput = $this->option('expires-at');

        // Parse subject (format: App\Models\User:1)
        if (!str_contains($subjectInput, ':')) {
            $this->error("Invalid subject format. Expected format: App\\\\Models\\\\User:1");

            return Command::FAILURE;
        }

        [$subjectClass, $subjectId] = explode(':', $subjectInput, 2);

        // Validate subject class exists
        if (!class_exists($subjectClass)) {
            $this->error("Subject model class [{$subjectClass}] does not exist.");

            return Command::FAILURE;
        }

        // Find subject model
        $subject = $subjectClass::find($subjectId);

        if (!$subject) {
            $this->error("Subject with ID {$subjectId} not found.");

            return Command::FAILURE;
        }

        // Parse resource if provided (format: App\Models\Project:1)
        $resource = null;
        $resourceType = null;
        $resourceId = null;

        if ($resourceInput) {
            if (!str_contains($resourceInput, ':')) {
                $this->error("Invalid resource format. Expected format: App\\\\Models\\\\Project:1");

                return Command::FAILURE;
            }

            [$resourceType, $resourceId] = explode(':', $resourceInput, 2);

            if (!class_exists($resourceType)) {
                $this->error("Resource model class [{$resourceType}] does not exist.");

                return Command::FAILURE;
            }

            $resource = $resourceType::find($resourceId);

            if (!$resource) {
                $this->error("Resource with ID {$resourceId} not found.");

                return Command::FAILURE;
            }
        }

        // Parse role input to check if it contains resource_type
        if (is_numeric($roleInput)) {
            $role = Role::find($roleInput);
        } else {
            // If the role is a name, we need to check if it is a global role or a resource-specific role
            $role = Role::where('name', $roleInput)->where('resource_type', $resourceType)->first();
        }

        if (!$role || $role->resource_type !== $resourceType) {
            $this->error("Role '{$roleInput}' not found or does not match the resource type" . ($resourceType ? " '{$resourceType}'." : " 'global'."));

            return Command::FAILURE;
        }

        // Parse expires_at if provided
        $expiresAt = null;
        if ($expiresAtInput) {
            try {
                $expiresAt = Carbon::parse($expiresAtInput);
            } catch (\Exception $e) {
                $this->error("Invalid expires-at format. Please use a valid datetime string (e.g., '2025-12-31 23:59:59' or '2025-12-31').");

                return Command::FAILURE;
            }
        }

        // If subject uses HasRoles trait, use its assignRole method
        if (method_exists($subject, 'assignRole')) {
            try {
                $subject->assignRole($role, $resource, $expiresAt);
                $expiresAtMessage = $expiresAt ? " (expires at {$expiresAt->format('Y-m-d H:i:s')})" : '';
                $this->info("Role `{$role->name}` assigned to {$subjectClass} ID {$subjectId}" . ($resource ? " on {$resourceType} ID {$resourceId}" : '') . $expiresAtMessage . " successfully.");

                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->error("Failed to assign role: {$e->getMessage()}");

                return Command::FAILURE;
            }
        }

        // Fallback: Insert directly into acl_role_assignments table if HasRoles trait is not available
        try {
            $data = [
                'subject_type' => $subjectClass,
                'subject_id' => $subjectId,
                'role_id' => $role->id,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'updated_at' => now(),
            ];

            if ($expiresAt) {
                $data['expires_at'] = $expiresAt;
            }

            DB::table('acl_role_assignments')->updateOrInsert(
                [
                    'subject_type' => $subjectClass,
                    'subject_id' => $subjectId,
                    'role_id' => $role->id,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                ],
                array_merge($data, [
                    'created_at' => now(),
                ])
            );

            $expiresAtMessage = $expiresAt ? " (expires at {$expiresAt->format('Y-m-d H:i:s')})" : '';
            $this->info("Role `{$role->name}` assigned to {$subjectClass} ID {$subjectId}" . ($resource ? " on {$resourceType} ID {$resourceId}" : '') . $expiresAtMessage . " successfully.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to assign role: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
