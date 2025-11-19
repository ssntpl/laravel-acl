# Laravel ACL

Laravel ACL is a framework-agnostic access control layer for Laravel 10/11/12 projects. It provides global and resource-scoped roles, explicit ALLOW/DENY permissions, permission implication trees, and cache-friendly lookups that plug straight into your existing Eloquent models through a reusable trait.

## Features

- Global or resource-scoped role assignments stored via polymorphic pivots.
- Permission inheritance (parent → child) with cached graph traversal.
- Explicit ALLOW/DENY effects per role-permission pair layered on top of implied permissions.
- Role assignment expirations and helper trait (`HasRoles`) for Eloquent subjects.
- Cache invalidation hooks plus an `acl:cache-reset` artisan command.
- First-class artisan tooling for creating permissions, roles, and assignments.
- HTTP middleware for checking global roles or permissions in routes/controllers.

## Requirements

- PHP ^8.0
- Laravel framework ^10.0 | ^11.0 | ^12.0

## Installation

```bash
composer require ssntpl/laravel-acl
```

1. (Optional) Publish the config and migrations:
   ```bash
   php artisan vendor:publish --tag=acl-config
   php artisan vendor:publish --tag=acl-migrations
   ```
2. Run the migrations:
   ```bash
   php artisan migrate
   ```

## Configuration (`config/acl.php`)

```php
return [
    'cache_ttl' => env('ACL_CACHE_TTL', 86400),
];
```

- `cache_ttl` – lifetime (seconds) for cached permission trees and role lookups.

## Database schema

Publishing the migration adds:

| Table | Key columns | Purpose |
| --- | --- | --- |
| `acl_roles` | `name`, `resource_type`, `description` | Defines each role; `resource_type` is `null` for global roles. |
| `acl_permissions` | `name`, `resource_type` | Defines permissions; names are unique. |
| `acl_role_permissions` | `role_id`, `permission_id`, `effect` | Pivot with ALLOW/DENY effect per permission. |
| `acl_role_assignments` | `subject_type`, `subject_id`, `role_id`, optional `resource_*`, `expires_at` | Links a subject (e.g., user) to a role, optionally scoped to a resource, with expiration support. |
| `acl_permissions_implications` | `parent_permission_id`, `child_permission_id` | Models permission implication trees (grant parent ⇒ grant child). |

## Usage

### 1. Add `HasRoles` to the authenticatable model

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Ssntpl\LaravelAcl\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### 2. Create permissions

Interactive artisan flow:

```bash
php artisan acl:create-permission articles.publish "App\\Models\\Project" --implied="articles.read,articles.list"
```

Programmatically:

```php
use Ssntpl\LaravelAcl\Models\Permission;

$publish = Permission::create(['name' => 'articles.publish']);
$read = Permission::firstOrCreate(['name' => 'articles.read']);
$publish->children()->attach($read); // Publish implies read
```

### 3. Create roles and attach permissions

```bash
php artisan acl:create-role admin "App\\Models\\Project" "articles.read|articles.publish|comments.moderate"
```

In PHP you can use either `syncPermissions()` (implicit ALLOW) or `syncPermissionsWithEffect()`:

```php
use Ssntpl\LaravelAcl\Models\Role;

$role = Role::firstOrCreate([
    'name' => 'admin',
    'resource_type' => App\Models\Project::class,
]);

$role->syncPermissionsWithEffect([
    $publish->id => 'ALLOW',
    $read->id => 'ALLOW',
]);
```

### 4. Assign roles

Using the trait:

```php
$user = User::find(1);
$project = Project::find(42);

$user->assignRole('admin', $project);                    // scoped role
$user->assignRole('super-admin', null, now()->addMonth()); // global role with expiration
```

Using the command (handy for ops/support teams):

```bash
php artisan acl:assign-role admin App\\Models\\User:1 App\\Models\\Project:42 --expires-at="2025-12-31 23:59:59"
```

### 5. Check roles & permissions

```php
if ($user->hasRole('admin', $project)) {
    // subject is an admin of this project
}

$role = $user->getRole(); // global role assignment (resource = null)

if ($role && $role->can('articles.publish')) {
    // allowed via direct or implied permission (and not explicitly denied)
}
```

`removeRole($resource = null)` deletes an assignment, and calling `getRole($resource)` returns the underlying `Role` model instance if one exists and is not expired.

## HTTP middleware

Register the middleware aliases in `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    'check_global_role' => \Ssntpl\LaravelAcl\Http\Middleware\CheckGlobalRole::class,
    'check_global_permission' => \Ssntpl\LaravelAcl\Http\Middleware\CheckGlobalPermission::class,
];
```

Usage:

```php
Route::get('/admin', fn () => 'ok')->middleware('check_global_role:admin|manager');
Route::post('/articles', fn () => 'ok')->middleware('check_global_permission:articles.publish|articles.create');
```

Both middleware assume global assignments (resource is `null`) when evaluating the authenticated subject.

> The authenticated guard’s model must use the `HasRoles` trait (or at least expose compatible `hasRole`/`getRole` methods) because the middleware works directly with `Auth::user()` without re-querying the database.

## Caching & invalidation

Role permissions and implied permission trees are cached per record using the configured TTL. Cache invalidation happens automatically when:

- Permissions are created, updated, deleted, or their implication edges change.
- Roles are updated or their pivot records change.
- The `acl:cache-reset` command is executed.

Run a full reset manually with:

```bash
php artisan acl:cache-reset
```

## Artisan command reference

| Command | Description |
| --- | --- |
| `acl:create-permission` | Create/update a permission and optionally attach implied permissions (supports interactive prompts). |
| `acl:create-role` | Create a role and assign permissions in one step. |
| `acl:assign-role` | Attach a role to a subject/resource pair with optional expiration. |
| `acl:cache-reset` | Flush cached permissions and role lookups. |

## Support & contributing

- Issues: [https://github.com/ssntpl/laravel-acl/issues](https://github.com/ssntpl/laravel-acl/issues)
- Source: [https://github.com/ssntpl/laravel-acl](https://github.com/ssntpl/laravel-acl)

PRs are welcome. Please include reproduction steps or tests when reporting/patching bugs.
