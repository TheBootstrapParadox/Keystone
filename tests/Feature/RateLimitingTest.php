<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Controllers\LoginController;
use BSPDX\Keystone\Http\Controllers\PasskeyAuthController;
use BSPDX\Keystone\Http\Controllers\TwoFactorAuthController;
use BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for keystone.rate_limiting.* — proves each ceiling and the
 * lockout window actually throttle the Keystone-owned auth endpoints.
 */
class RateLimitingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
    }

    // ──────────────────────────────────────────────
    // max_login_attempts (passwordless TOTP login)
    // ──────────────────────────────────────────────

    #[Test]
    public function passwordless_login_locks_out_after_max_login_attempts(): void
    {
        config([
            'keystone.features.passwordless_login' => true,
            'keystone.rate_limiting.max_login_attempts' => 2,
            'keystone.rate_limiting.lockout_duration' => 1,
        ]);

        Route::middleware('web')->post('/login/totp', [LoginController::class, 'authenticateWithTotp']);

        $payload = ['email' => 'nobody@example.com', 'totp_code' => '123456'];

        // Two failed attempts (invalid credentials -> 401).
        $this->postJson('/login/totp', $payload)->assertStatus(401);
        $this->postJson('/login/totp', $payload)->assertStatus(401);

        // Third is blocked by the limiter.
        $this->postJson('/login/totp', $payload)->assertStatus(429);
    }

    #[Test]
    public function successful_passwordless_login_clears_attempts(): void
    {
        config([
            'keystone.features.passwordless_login' => true,
            'keystone.rate_limiting.max_login_attempts' => 2,
        ]);

        Route::middleware('web')->post('/login/totp', [LoginController::class, 'authenticateWithTotp']);

        $user = User::factory()->create([
            'allow_totp_login' => true,
            'two_factor_secret' => encrypt('SECRET'),
            'two_factor_confirmed_at' => now(),
        ]);

        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('setWindow');
        // First call fails, second succeeds.
        $google2fa->shouldReceive('verifyKey')->andReturn(false, true);
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $payload = ['email' => $user->email, 'totp_code' => '123456'];

        $this->postJson('/login/totp', $payload)->assertStatus(401);
        $this->postJson('/login/totp', $payload)->assertOk();

        // Counter was cleared on success, so a subsequent failure is not blocked.
        $google2fa2 = Mockery::mock();
        $google2fa2->shouldReceive('setWindow');
        $google2fa2->shouldReceive('verifyKey')->andReturn(false);
        $this->app->instance('pragmarx.google2fa', $google2fa2);

        $this->postJson('/login/totp', $payload)->assertStatus(401);
    }

    // ──────────────────────────────────────────────
    // max_2fa_attempts (2FA confirm)
    // ──────────────────────────────────────────────

    #[Test]
    public function two_factor_confirm_locks_out_after_max_2fa_attempts(): void
    {
        config([
            'keystone.features.two_factor' => true,
            'keystone.rate_limiting.max_2fa_attempts' => 2,
        ]);

        Route::middleware(['web', 'auth'])
            ->post('/user/confirmed-two-factor-authentication', [TwoFactorAuthController::class, 'confirm']);

        $user = User::factory()->create([
            'two_factor_secret' => encrypt('SECRET'),
        ]);

        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('setWindow');
        $google2fa->shouldReceive('verifyKey')->andReturn(false);
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $payload = ['code' => '000000'];

        $this->actingAs($user)->postJson('/user/confirmed-two-factor-authentication', $payload)->assertStatus(422);
        $this->actingAs($user)->postJson('/user/confirmed-two-factor-authentication', $payload)->assertStatus(422);

        // Third attempt is throttled (429) before reaching verification.
        $this->actingAs($user)->postJson('/user/confirmed-two-factor-authentication', $payload)->assertStatus(429);
    }

    // ──────────────────────────────────────────────
    // max_passkey_attempts (passkey login)
    // ──────────────────────────────────────────────

    #[Test]
    public function passkey_authentication_locks_out_after_max_passkey_attempts(): void
    {
        config([
            'keystone.features.passkeys' => true,
            'keystone.rate_limiting.max_passkey_attempts' => 2,
        ]);

        Route::middleware('web')->post('/passkey/authenticate', [PasskeyAuthController::class, 'authenticate']);

        $this->mock(PasskeyServiceInterface::class)
            ->shouldReceive('findPasskeyToAuthenticate')
            ->andReturn(null);

        $payload = ['credential' => '{}', 'options' => '{}'];

        $this->postJson('/passkey/authenticate', $payload)->assertStatus(401);
        $this->postJson('/passkey/authenticate', $payload)->assertStatus(401);

        $this->postJson('/passkey/authenticate', $payload)->assertStatus(429);
    }
}
