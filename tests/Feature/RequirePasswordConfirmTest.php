<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class RequirePasswordConfirmTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'password-confirm'])
            ->get('/test-password-confirm', function () {
                return response()->json(['message' => 'ok']);
            })->name('test.password-confirm');

        // Used to test password-confirm on DELETE routes with a URL parameter.
        // We cannot use the real /user/passkeys/{passkey} route here because
        // laravel/passkeys (a Fortify dependency) registers a global Route::bind('passkey', ...)
        // binding that fires a 404 before middleware runs when the passkey ID does not exist.
        Route::middleware(['web', 'auth', 'password-confirm'])
            ->delete('/test-passkey-delete/{passkeyId}', function () {
                return response()->json(['message' => 'ok']);
            })->name('test.passkey.delete');
    }

    #[Test]
    public function middleware_passes_when_feature_is_disabled(): void
    {
        config(['keystone.profile.require_password_confirm' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/test-password-confirm')
            ->assertOk();
    }

    #[Test]
    public function middleware_passes_when_password_confirmed_within_timeout(): void
    {
        config([
            'keystone.profile.require_password_confirm' => true,
            'keystone.session.password_timeout' => 10800,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson('/test-password-confirm')
            ->assertOk();
    }

    #[Test]
    public function api_request_gets_423_when_password_not_confirmed(): void
    {
        config(['keystone.profile.require_password_confirm' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/test-password-confirm')
            ->assertStatus(423)
            ->assertJson(['message' => 'Password confirmation required.']);
    }

    #[Test]
    public function web_request_redirects_when_password_not_confirmed(): void
    {
        config(['keystone.profile.require_password_confirm' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/test-password-confirm')
            ->assertRedirect(route('password.confirm'));
    }

    #[Test]
    public function middleware_treats_expired_confirmation_as_unconfirmed(): void
    {
        config([
            'keystone.profile.require_password_confirm' => true,
            'keystone.session.password_timeout' => 10800,
        ]);

        $user = User::factory()->create();
        $expiredAt = time() - 10801;

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => $expiredAt])
            ->getJson('/test-password-confirm')
            ->assertStatus(423);
    }

    #[Test]
    public function auth_preferences_route_requires_password_confirm(): void
    {
        config(['keystone.profile.require_password_confirm' => true]);

        $user = User::factory()->create();
        $profilePath = config('keystone.profile.path', '/profile');

        $this->actingAs($user)
            ->putJson("{$profilePath}/auth-preferences", [])
            ->assertStatus(423);
    }

    #[Test]
    public function two_factor_store_requires_password_confirm(): void
    {
        config(['keystone.profile.require_password_confirm' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/user/two-factor-authentication')
            ->assertStatus(423);
    }

    #[Test]
    public function two_factor_destroy_requires_password_confirm(): void
    {
        config(['keystone.profile.require_password_confirm' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/user/two-factor-authentication')
            ->assertStatus(423);
    }

    #[Test]
    public function passkey_store_requires_password_confirm(): void
    {
        config(['keystone.profile.require_password_confirm' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/user/passkeys', [])
            ->assertStatus(423);
    }

    #[Test]
    public function passkey_destroy_requires_password_confirm(): void
    {
        config(['keystone.profile.require_password_confirm' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/test-passkey-delete/some-passkey-id')
            ->assertStatus(423);
    }
}
