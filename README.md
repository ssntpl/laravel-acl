# SSNTPL Permissions

A Laravel package for managing dynamic roles and permissions on resources.

## Description

SSNTPL Permissions is a Laravel package that provides a flexible role and permission system with support for resource-specific roles. It allows you to assign roles to users on specific resources (like projects, organizations, etc.) or globally.

## Features

- ✅ Dynamic role creation and management
- ✅ Permission-based access control
- ✅ Resource-specific roles (roles can be scoped to specific resources)
- ✅ Global roles (roles without resource scope)
- ✅ Artisan commands for role and permission management
- ✅ Trait-based integration with Eloquent models
- ✅ HTTP middleware for route protection
- ✅ Configurable role behavior

## Installation

Install the package via Composer:

```bash
composer require ssntpl/permissions
```

### Publish Configuration and Migrations

Publish the configuration file:

```bash
php artisan vendor:publish --tag=permissions-config
```

Publish the migrations:

```bash
php artisan vendor:publish --tag=permissions-migrations
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

The configuration file `config/permissions.php` contains:

```php
return [
    'role_on_resource' => true,  // Enable resource-specific roles
    'user_model' => "App\\Models\\User"  // Your user model
];
```

### Configuration Options

- `role_on_resource`: When `true`, roles are scoped to specific resources. When `false`, roles are global.
- `user_model`: The user model class that will use roles.

## Usage

### 1. Add the HasRoles Trait

Add the `HasRoles` trait to your User model (or any model that needs roles):

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Ssntpl\Permissions\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    
    // ... rest of your model
}
```

### 2. Creating Roles and Permissions

#### Using Artisan Commands

Create a permission:

```bash
php artisan permissions:create-permission "manage users"
```

Create a role with permissions:

```bash
php artisan permissions:create-role "admin" "App\\Models\\Project" "create|read|update|delete"
```

#### Programmatically

```php
use Ssntpl\Permissions\Models\Role;
use Ssntpl\Permissions\Models\Permission;

// Create permissions
$createPermission = Permission::create(['name' => 'create']);
$readPermission = Permission::create(['name' => 'read']);

// Create a role
$adminRole = Role::create([
    'name' => 'admin',
    'resource_type' => 'App\\Models\\Project'
]);

// Assign permissions to role
$adminRole->syncPermissions(collect([$createPermission, $readPermission]));
```

### 3. Assigning Roles to Users

#### Resource-Specific Roles

```php
$user = User::find(1);
$project = Project::find(1);

// Assign role to user for specific resource
$user->assignRole('admin', $project);

// Or using role object
$adminRole = Role::where('name', 'admin')->first();
$user->assignRole($adminRole, $project);
```

#### Global Roles (when role_on_resource is false)

```php
$user = User::find(1);

// Assign global role
$user->assignRole('admin');
```

### 4. Checking Roles and Permissions

#### Check if user has specific role

```php
$user = User::find(1);
$project = Project::find(1);

// Check role on specific resource
if ($user->hasRole('admin', $project)) {
    // User is admin of this project
}

// Check global role (when role_on_resource is false)
if ($user->hasRole('admin')) {
    // User has global admin role
}
```

#### Check if user has any of multiple roles

```php
// Check multiple roles (OR condition)
if ($user->hasAnyRole(['admin', 'manager'], $project)) {
    // User is either admin or manager of this project
}

// Using pipe-separated string
if ($user->hasAnyRole('admin|manager', $project)) {
    // Same as above
}
```

#### Get user's role name

```php
$roleName = $user->getRoleName($project);
// Returns: 'admin', 'manager', etc., or empty string if no role
```

### 5. Managing Roles

#### Remove role from user

```php
$user->removeRole($project);  // Remove role for specific resource
$user->removeRole();          // Remove global role
```

#### Get available roles for a resource type

```php
$user = User::find(1);
$availableRoles = $user->roles(); // Returns roles for user's class
```

## Database Schema

The package creates four tables:

### `roles`
- `id` - Primary key
- `name` - Role name
- `resource_type` - Model class name (nullable for global roles)
- `timestamps`
- Unique constraint on `[name, resource_type]`

### `permissions`
- `id` - Primary key
- `name` - Permission name (unique)
- `timestamps`

### `role_has_permissions`
- `role_id` - Foreign key to roles table
- `permission_id` - Foreign key to permissions table
- Unique constraint on `[role_id, permission_id]`

### `model_resource_roles`
- `role_id` - Foreign key to roles table
- `model_id` - ID of the model (user)
- `model_type` - Model class name
- `resource_id` - ID of the resource (nullable for global roles)
- `resource_type` - Resource class name (nullable for global roles)

## API Reference

### HasRoles Trait Methods

| Method | Parameters | Description |
|--------|------------|-------------|
| `assignRole($role, $resource = null)` | Role name/object, Resource model | Assign role to user |
| `removeRole($resource = null)` | Resource model | Remove role from user |
| `hasRole($role, $resource = null)` | Role name/object, Resource model | Check if user has role |
| `hasAnyRole($roles, $resource = null)` | Array/string of roles, Resource model | Check if user has any of the roles |
| `getRoleName($resource = null)` | Resource model | Get user's role name |
| `roles()` | None | Get available roles for user's class |
| `role($resource = null)` | Resource model | Get user's role relationship |

### Role Model Methods

| Method | Parameters | Description |
|--------|------------|-------------|
| `syncPermissions($permissions)` | Collection of permissions | Sync permissions to role |
| `findResource($resourceType, $id)` | Class name, ID | Find resource by type and ID |

## Artisan Commands

### Create Permission

```bash
php artisan permissions:create-permission {name}
```

**Arguments:**
- `name` - The name of the permission

**Interactive Mode:**
Run without arguments to use interactive prompts:
```bash
php artisan permissions:create-permission
```

### Create Role

```bash
php artisan permissions:create-role {name} {resource_type} {permissions}
```

**Arguments:**
- `name` - The name of the role
- `resource_type` - Type of the resource (model class)
- `permissions` - Pipe-separated list of permissions (e.g., "create|read|update")

**Interactive Mode:**
Run without arguments to use interactive prompts:
```bash
php artisan permissions:create-role
```

## Examples

### Example 1: Project Management System

```php
// Create roles and permissions for project management
$project = Project::find(1);
$user = User::find(1);

// Assign user as project admin
$user->assignRole('admin', $project);

// Check permissions
if ($user->hasRole('admin', $project)) {
    // User can manage this project
}

// Check multiple roles
if ($user->hasAnyRole(['admin', 'manager'], $project)) {
    // User has management access to this project
}
```

### Example 2: Organization Roles

```php
// Different roles for different organizations
$org1 = Organization::find(1);
$org2 = Organization::find(2);
$user = User::find(1);

// User can be admin in one org and member in another
$user->assignRole('admin', $org1);
$user->assignRole('member', $org2);

// Check roles
$user->hasRole('admin', $org1);  // true
$user->hasRole('admin', $org2);  // false
$user->hasRole('member', $org2); // true
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

- **Issues**: [GitHub Issues](https://github.com/ssntpl/permissions/issues)
- **Source**: [GitHub Repository](https://github.com/ssntpl/permissions)

## Author

**Abhishek Sharma**
- Email: abhishek.sharma@ssntpl.in
- Website: [https://ssntpl.com](https://ssntpl.com)

## HTTP Middleware

The package provides three middleware classes for route protection:

### 1. Role Middleware

Protects routes based on user roles:

```php
// Register in app/Http/Kernel.php
protected $middlewareAliases = [
    'role' => \Ssntpl\Permissions\Http\Middleware\RoleMiddleware::class,
];

// Usage in routes
Route::get('/admin', function () {
    // Only users with 'admin' role can access
})->middleware('role:admin,App\\Models\\Project');

// Multiple roles (OR condition)
Route::get('/management', function () {
    // Users with 'admin' OR 'manager' role can access
})->middleware('role:admin|manager,App\\Models\\Project');
```

### 2. Permission Middleware

Protects routes based on user permissions:

```php
// Register in app/Http/Kernel.php
protected $middlewareAliases = [
    'permission' => \Ssntpl\Permissions\Http\Middleware\PermissionMiddleware::class,
];

// Usage in routes
Route::post('/projects', function () {
    // Only users with 'create' permission can access
})->middleware('permission:create,App\\Models\\Project');

// Multiple permissions (OR condition)
Route::get('/projects', function () {
    // Users with 'read' OR 'list' permission can access
})->middleware('permission:read|list,App\\Models\\Project');
```

### 3. Role or Permission Middleware

Protects routes based on either roles OR permissions:

```php
// Register in app/Http/Kernel.php
protected $middlewareAliases = [
    'role_or_permission' => \Ssntpl\Permissions\Http\Middleware\RoleOrPermissionMiddleware::class,
];

// Usage in routes
Route::delete('/projects/{id}', function () {
    // Users with 'admin' role OR 'delete' permission can access
})->middleware('role_or_permission:admin|delete,App\\Models\\Project');
```

### Middleware Parameters

All middleware accept these parameters:
1. **Role/Permission/Mixed** - Role names, permission names, or both (pipe-separated)
2. **Resource Type** - Fully qualified class name of the resource model

**Note:** The middleware expects `resource_id` in the request for resource-specific roles.

## Configuration Modes

### Resource-Specific Roles (`role_on_resource: true`)

Roles are scoped to specific resources. Users can have different roles for different resources:

```php
$user->assignRole('admin', $project1);  // Admin of project1
$user->assignRole('member', $project2); // Member of project2

$user->hasRole('admin', $project1);  // true
$user->hasRole('admin', $project2);  // false
```

### Global Roles (`role_on_resource: false`)

Roles are global across the application. Users have one role system-wide:

```php
$user->assignRole('admin');  // Global admin

$user->hasRole('admin');  // true (anywhere in the app)
```

## Advanced Usage

### Working with Permissions

```php
use Ssntpl\Permissions\Models\Role;
use Ssntpl\Permissions\Models\Permission;

// Create permissions
$createPerm = Permission::create(['name' => 'create']);
$readPerm = Permission::create(['name' => 'read']);
$updatePerm = Permission::create(['name' => 'update']);
$deletePerm = Permission::create(['name' => 'delete']);

// Create role with permissions
$adminRole = Role::create([
    'name' => 'admin',
    'resource_type' => 'App\\Models\\Project'
]);

// Sync permissions to role
$adminRole->syncPermissions(collect([
    $createPerm, $readPerm, $updatePerm, $deletePerm
]));
```

### Checking Permissions

```php
// Get user's role for a resource
$userRole = $user->role($project)->first();

// Check if role has specific permission
if ($userRole && $userRole->role->permissions()->where('name', 'create')->exists()) {
    // User can create
}

// Get all permissions for user's role
$permissions = $userRole?->role?->permissions ?? collect();
```

### Dynamic Resource Finding

```php
use Ssntpl\Permissions\Models\Role;

// Find resource dynamically
$project = Role::findResource('App\\Models\\Project', 1);

if ($project) {
    $user->assignRole('admin', $project);
}
```

## Error Handling

The package provides proper error handling:

```php
try {
    $user->assignRole('admin', $project);
} catch (Exception $e) {
    // Handle role assignment errors
    Log::error('Role assignment failed: ' . $e->getMessage());
}
```

## Security Considerations

1. **Input Validation**: Always validate role and permission names
2. **Resource Ownership**: Verify resource ownership before role operations
3. **Middleware Order**: Place authentication middleware before permission middleware
4. **SQL Injection**: The package uses Eloquent ORM to prevent SQL injection

## Performance Tips

1. **Eager Loading**: Load roles with permissions to reduce queries
```php
$user->load('modelRoles.role.permissions');
```

2. **Caching**: Cache frequently accessed roles and permissions
```php
$userRoles = Cache::remember("user.{$user->id}.roles", 3600, function () use ($user) {
    return $user->modelRoles()->with('role.permissions')->get();
});
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.