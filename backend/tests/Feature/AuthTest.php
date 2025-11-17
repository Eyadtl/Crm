<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'tester@example.com',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user']);
    }

    public function test_admin_can_invite_new_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::factory()->create([
            'status' => 'active',
            'password_hash' => Hash::make('password'),
        ]);

        $roleId = Role::where('slug', 'admin')->value('id');
        $admin->roles()->sync([$roleId => ['assigned_by' => $admin->id, 'assigned_at' => now()]]);

        Mail::fake();

        $payload = [
            'name' => 'Invited User',
            'email' => 'invited@example.com',
            'roles' => ['viewer'],
        ];

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/auth/invite', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['message' => 'Invitation sent.']);
    }
}
