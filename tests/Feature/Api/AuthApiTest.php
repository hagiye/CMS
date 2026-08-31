<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_receive_a_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'editor@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => ' EDITOR@EXAMPLE.COM ',
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.abilities', ['bookmarks:read', 'bookmarks:write'])
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'abilities', 'user' => ['id', 'name', 'email']]]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'abilities' => '["bookmarks:read","bookmarks:write"]',
        ]);
    }

    public function test_login_rejects_invalid_credentials_and_invalid_input(): void
    {
        User::factory()->create([
            'email' => 'editor@example.com',
            'password' => 'secret123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'editor@example.com',
            'password' => 'incorrect',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials.']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'incorrect',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_log_out_and_revoke_the_current_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('current-device')->plainTextToken;
        $user->createToken('other-device');

        $this->withToken($plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Logged out.']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'other-device',
        ]);
    }

    public function test_logout_requires_a_valid_token(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->withToken('invalid-token')
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'limited@example.com',
            'password' => 'secret123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'limited@example.com',
                'password' => 'incorrect',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'limited@example.com',
            'password' => 'incorrect',
        ])->assertTooManyRequests();
    }
}
