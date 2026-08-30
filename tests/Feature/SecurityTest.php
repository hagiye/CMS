<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ContentNode;
use App\Models\Document;
use App\Models\User;
use App\Policies\ContentNodePolicy;
use App\Policies\DocumentPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_editorial_roles_can_access_filament(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse(User::factory()->make()->canAccessPanel($panel));
        $this->assertTrue(User::factory()->admin()->make()->canAccessPanel($panel));
        $this->assertTrue(User::factory()->editor()->make()->canAccessPanel($panel));
        $this->assertTrue(User::factory()->reviewer()->make()->canAccessPanel($panel));
    }

    public function test_editorial_policies_enforce_the_role_matrix(): void
    {
        $admin = User::factory()->admin()->make();
        $editor = User::factory()->editor()->make();
        $reviewer = User::factory()->reviewer()->make();
        $reader = User::factory()->make();
        $node = new ContentNode;
        $document = new Document;
        $nodes = new ContentNodePolicy;
        $documents = new DocumentPolicy;

        $this->assertTrue($nodes->create($admin));
        $this->assertTrue($nodes->create($editor));
        $this->assertFalse($nodes->create($reviewer));
        $this->assertFalse($nodes->viewAny($reader));

        $this->assertTrue($nodes->update($editor, $node));
        $this->assertTrue($nodes->update($reviewer, $node));
        $this->assertFalse($nodes->delete($editor, $node));
        $this->assertTrue($nodes->delete($admin, $node));
        $this->assertFalse($nodes->publish($editor, $node));
        $this->assertTrue($nodes->publish($reviewer, $node));

        $this->assertTrue($documents->update($reviewer, $document));
        $this->assertFalse($documents->delete($reviewer, $document));
    }

    public function test_sanctum_bookmark_routes_require_the_correct_ability(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['bookmarks:read']);

        $this->postJson('/api/v1/bookmarks', ['content_node_id' => 1])
            ->assertForbidden();
    }

    public function test_role_is_cast_to_the_enum(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame(UserRole::Admin, $user->role);
    }

    public function test_public_search_is_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42']);

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->getJson('/api/v1/search?q=missing')->assertOk();
        }

        $this->getJson('/api/v1/search?q=missing')->assertTooManyRequests();
    }
}
