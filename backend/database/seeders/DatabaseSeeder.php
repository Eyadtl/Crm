<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $roles = [
            ['slug' => 'admin', 'name' => 'Admin', 'description' => 'Full system access'],
            ['slug' => 'manager', 'name' => 'Manager', 'description' => 'Manage projects and teams'],
            ['slug' => 'editor', 'name' => 'Editor', 'description' => 'Update projects and contacts'],
            ['slug' => 'viewer', 'name' => 'Viewer', 'description' => 'Read-only access'],
        ];

        foreach ($roles as $roleData) {
            Role::query()->updateOrCreate(
                ['slug' => $roleData['slug']],
                array_merge($roleData, ['updated_at' => $now, 'created_at' => $now])
            );
        }

        $statuses = [
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'New', 'position' => 1, 'is_default' => true, 'is_terminal' => false],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Qualified', 'position' => 2, 'is_default' => false, 'is_terminal' => false],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Negotiation', 'position' => 3, 'is_default' => false, 'is_terminal' => false],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Won', 'position' => 4, 'is_default' => false, 'is_terminal' => true],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Lost', 'position' => 5, 'is_default' => false, 'is_terminal' => true],
        ];

        $existingStatuses = DB::table('deal_statuses')->pluck('name')->all();
        if (empty($existingStatuses)) {
            DB::table('deal_statuses')->insert($statuses);
        }

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'email_hot_storage_months'],
            ['value' => json_encode(6), 'updated_at' => $now]
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Arabia Admin',
                'password_hash' => Hash::make('password'),
                'timezone' => 'UTC',
                'status' => 'active',
                'invited_at' => $now,
                'last_login_at' => $now,
            ]
        );

        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
        if ($adminRoleId) {
            $admin->roles()->syncWithoutDetaching([
                $adminRoleId => [
                    'assigned_by' => $admin->id,
                    'assigned_at' => $now,
                ],
            ]);
        }
    }
}
