<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_editorial_user_can_log_in_to_filament(): void
    {
        $user = User::factory()->editor()->create([
            'email' => 'editor@example.com',
            'password' => 'secret123',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'editor@example.com',
                'password' => 'secret123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($user);
    }

    public function test_filament_rejects_invalid_credentials_and_users_without_an_editorial_role(): void
    {
        User::factory()->editor()->create([
            'email' => 'editor@example.com',
            'password' => 'secret123',
        ]);
        User::factory()->create([
            'email' => 'reader@example.com',
            'password' => 'secret123',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'editor@example.com',
                'password' => 'incorrect',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'reader@example.com',
                'password' => 'secret123',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_editorial_user_can_log_out_of_filament(): void
    {
        $user = User::factory()->reviewer()->create();
        $this->actingAs($user);

        $this->post(route('filament.admin.auth.logout'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }
}
