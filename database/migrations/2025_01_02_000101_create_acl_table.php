<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acl_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');     // e.g. "admin", "viewer", "editor"
            $table->string('resource_type')->nullable(); // e.g. "App\Models\Team", "App\Models\Board", or NULL for global
            $table->string('description')->nullable();

            $table->unique(['name', 'resource_type']);
        });
        
        Schema::create('acl_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. "create", "team.read", "team.update", "team.delete"
            $table->string('resource_type')->nullable(); // e.g. "App\Models\Team", "App\Models\Board", or NULL for global
        });
        
        Schema::create('acl_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('acl_roles')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('acl_permissions')->onDelete('cascade');
            $table->enum('effect', ['ALLOW', 'DENY'])->default('ALLOW');

            $table->primary(['role_id', 'permission_id']);
        });
        
        Schema::create('acl_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');  // user, group, etc.
            $table->foreignId('role_id')->constrained('acl_roles')->onDelete('restrict')->onUpdate('cascade');
            $table->nullableMorphs('resource');  // team, board, project, etc.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'role_id', 'resource_type', 'resource_id'], 'acl_role_assignments_unique');
        });

        Schema::create('acl_permissions_implications', function (Blueprint $table) {
            $table->foreignId('parent_permission_id')->constrained('acl_permissions')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('child_permission_id')->constrained('acl_permissions')->onDelete('cascade')->onUpdate('cascade');
            $table->primary(['parent_permission_id', 'child_permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_permissions_implications');
        Schema::dropIfExists('acl_role_assignments');
        Schema::dropIfExists('acl_role_permissions');
        Schema::dropIfExists('acl_permissions');
        Schema::dropIfExists('acl_roles');
    }
};
