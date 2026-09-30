<?php

namespace Database\Seeders;

use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use BSPDX\Keystone\Services\PermissionRegistrar;
use Illuminate\Database\Seeder;

class KeystoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates the baseline permissions and roles only. Demo users live in
     * KeystoneDemoUserSeeder so they are never created by accident.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions with title and description
        $permissions = [
            // User permissions
            ['name' => 'view-users', 'title' => 'View Users', 'description' => 'View user list and profiles'],
            ['name' => 'create-users', 'title' => 'Create Users', 'description' => 'Create new user accounts'],
            ['name' => 'edit-users', 'title' => 'Edit Users', 'description' => 'Modify existing user profiles'],
            ['name' => 'delete-users', 'title' => 'Delete Users', 'description' => 'Remove user accounts from the system'],

            // Role & Permission management
            ['name' => 'view-roles', 'title' => 'View Roles', 'description' => 'View available roles'],
            ['name' => 'create-roles', 'title' => 'Create Roles', 'description' => 'Create new roles'],
            ['name' => 'edit-roles', 'title' => 'Edit Roles', 'description' => 'Modify role permissions and settings'],
            ['name' => 'delete-roles', 'title' => 'Delete Roles', 'description' => 'Remove roles from the system'],
            ['name' => 'assign-roles', 'title' => 'Assign Roles', 'description' => 'Assign roles to users'],

            ['name' => 'view-permissions', 'title' => 'View Permissions', 'description' => 'View available permissions'],
            ['name' => 'create-permissions', 'title' => 'Create Permissions', 'description' => 'Create new permissions'],
            ['name' => 'edit-permissions', 'title' => 'Edit Permissions', 'description' => 'Modify permissions'],
            ['name' => 'delete-permissions', 'title' => 'Delete Permissions', 'description' => 'Remove permissions'],
            ['name' => 'assign-permissions', 'title' => 'Assign Permissions', 'description' => 'Assign permissions to roles or users'],

            // Content management examples
            ['name' => 'view-posts', 'title' => 'View Posts', 'description' => 'View published and draft posts'],
            ['name' => 'create-posts', 'title' => 'Create Posts', 'description' => 'Create new posts'],
            ['name' => 'edit-posts', 'title' => 'Edit Posts', 'description' => 'Edit post content and metadata'],
            ['name' => 'delete-posts', 'title' => 'Delete Posts', 'description' => 'Delete posts from the system'],
            ['name' => 'publish-posts', 'title' => 'Publish Posts', 'description' => 'Publish posts to make them publicly visible'],

            // Settings
            ['name' => 'view-settings', 'title' => 'View Settings', 'description' => 'View application settings'],
            ['name' => 'edit-settings', 'title' => 'Edit Settings', 'description' => 'Modify application configuration'],
        ];

        foreach ($permissions as $permissionData) {
            KeystonePermission::firstOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }

        // Create roles with title and description

        // Super Admin - has all permissions
        $superAdmin = KeystoneRole::firstOrCreate(
            ['name' => 'super-admin'],
            [
                'title' => 'Super Administrator',
                'description' => 'Full system access with all permissions. Can manage users, roles, permissions, and all content.',
            ]
        );
        $superAdmin->givePermissionTo(KeystonePermission::all());

        // Admin - most permissions except user/role management
        $admin = KeystoneRole::firstOrCreate(
            ['name' => 'admin'],
            [
                'title' => 'Administrator',
                'description' => 'Manage content, users, and settings. Cannot delete users or modify roles.',
            ]
        );
        $admin->givePermissionTo([
            'view-users',
            'create-users',
            'edit-users',
            'view-roles',
            'view-permissions',
            'view-posts',
            'create-posts',
            'edit-posts',
            'delete-posts',
            'publish-posts',
            'view-settings',
            'edit-settings',
        ]);

        // Editor - content management only
        $editor = KeystoneRole::firstOrCreate(
            ['name' => 'editor'],
            [
                'title' => 'Editor',
                'description' => 'Create, edit, and publish content. No access to users or settings.',
            ]
        );
        $editor->givePermissionTo([
            'view-posts',
            'create-posts',
            'edit-posts',
            'publish-posts',
        ]);

        // User - basic read permissions
        $user = KeystoneRole::firstOrCreate(
            ['name' => 'user'],
            [
                'title' => 'User',
                'description' => 'Basic access to view published content. Cannot create or modify anything.',
            ]
        );
        $user->givePermissionTo([
            'view-posts',
        ]);

        $this->command?->info('Keystone roles and permissions created successfully!');
    }
}
