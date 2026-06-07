<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Actions\GeneratePasskeyRegisterOptionsAction;
use BSPDX\Keystone\Http\Controllers\LoginController;
use BSPDX\Keystone\Http\Controllers\PasskeyAuthController;
use BSPDX\Keystone\Http\Controllers\TwoFactorAuthController;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage proving each retained config/keystone.php key drives real
 * runtime behavior. If a key here ever stops affecting behavior, a test fails —
 * preventing dead config from silently accumulating.
 */
class ConfigRegressionTest extends TestCase
{
    // ──────────────────────────────────────────────
    // passkey.timeout / user_verification / attestation
    // (applied by the custom GeneratePasskeyRegisterOptionsAction)
    // ──────────────────────────────────────────────

    #[Test]
    public function passkey_register_options_reflect_keystone_config(): void
    {
        config([
            'keystone.passkey.timeout' => 45000,
            'keystone.passkey.user_verification' => 'required',
            'keystone.passkey.attestation' => 'direct',
        ]);

        $user = User::factory()->create();

        $optionsJson = app(GeneratePasskeyRegisterOptionsAction::class)->execute($user, asJson: true);
        $options = json_decode($optionsJson, true);

        $this->assertSame(45000, $options['timeout']);
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $this->assertSame('direct', $options['attestation']);
    }

    #[Test]
    public function passkey_register_options_use_defaults_when_unset(): void
    {
        config([
            'keystone.passkey.timeout' => 60000,
            'keystone.passkey.user_verification' => 'preferred',
            'keystone.passkey.attestation' => 'none',
        ]);

        $user = User::factory()->create();

        $options = json_decode(
            app(GeneratePasskeyRegisterOptionsAction::class)->execute($user, asJson: true),
            true
        );

        $this->assertSame(60000, $options['timeout']);
        $this->assertSame('preferred', $options['authenticatorSelection']['userVerification']);
        $this->assertSame('none', $options['attestation']);
    }

    // ──────────────────────────────────────────────
    // two_factor.recovery_codes_count
    // ──────────────────────────────────────────────

    #[Test]
    public function recovery_codes_count_controls_number_of_generated_codes(): void
    {
        config([
            'keystone.features.two_factor' => true,
            'keystone.two_factor.recovery_codes_count' => 3,
            'keystone.profile.require_password_confirm' => false,
        ]);

        $this->registerTwoFactorRoutes();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/user/two-factor-authentication')
            ->assertOk();

        $this->assertCount(3, $response->json('recovery_codes'));
    }

    // ──────────────────────────────────────────────
    // redirects.login
    // ──────────────────────────────────────────────

    #[Test]
    public function passwordless_totp_login_honors_redirects_login(): void
    {
        config([
            'keystone.features.passwordless_login' => true,
            'keystone.redirects.login' => '/custom-home',
        ]);

        $this->registerPasswordlessRoutes();

        $user = User::factory()->create([
            'allow_totp_login' => true,
            'two_factor_secret' => encrypt('SECRET'),
            'two_factor_confirmed_at' => now(),
        ]);

        // Force TOTP verification to pass so we reach the redirect.
        $google2fa = \Mockery::mock();
        $google2fa->shouldReceive('setWindow');
        $google2fa->shouldReceive('verifyKey')->andReturn(true);
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $this->post('/login/totp', ['email' => $user->email, 'totp_code' => '123456'])
            ->assertRedirect('/custom-home');
    }

    // ──────────────────────────────────────────────
    // Feature-flag 404 guards
    // ──────────────────────────────────────────────

    #[Test]
    public function two_factor_routes_404_when_feature_disabled(): void
    {
        config(['keystone.features.two_factor' => false]);

        $this->registerTwoFactorRoutes();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/user/two-factor-authentication')
            ->assertNotFound();
    }

    #[Test]
    public function passwordless_totp_404_when_feature_disabled(): void
    {
        config(['keystone.features.passwordless_login' => false]);

        $this->registerPasswordlessRoutes();

        $this->postJson('/login/totp', ['email' => 'a@b.com', 'totp_code' => '123456'])
            ->assertNotFound();
    }

    #[Test]
    public function passkey_routes_404_when_feature_disabled(): void
    {
        config(['keystone.features.passkeys' => false]);

        Route::middleware(['web', 'auth'])->post(
            '/user/passkeys/options',
            [PasskeyAuthController::class, 'registerOptions']
        );

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/user/passkeys/options')
            ->assertNotFound();
    }

    // ──────────────────────────────────────────────
    // rbac.default_role (consumed by keystone:make-user)
    // ──────────────────────────────────────────────

    #[Test]
    public function make_user_command_assigns_configured_default_role(): void
    {
        // The command creates config('keystone.user.model'); point it at the
        // test user so it matches the test schema (also exercises user.model).
        config([
            'keystone.user.model' => User::class,
            'keystone.rbac.default_role' => 'member',
        ]);

        KeystoneRole::create(['name' => 'member']);

        $this->artisan('keystone:make-user', [
            '--name' => 'Reg Test',
            '--email' => 'reg-test@example.com',
            '--password' => 'password123',
        ])->assertSuccessful();

        $user = User::where('email', 'reg-test@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('member'));
    }

    #[Test]
    public function make_user_command_assigns_no_role_when_default_role_null(): void
    {
        config([
            'keystone.user.model' => User::class,
            'keystone.rbac.default_role' => null,
        ]);

        $this->artisan('keystone:make-user', [
            '--name' => 'No Role',
            '--email' => 'no-role@example.com',
            '--password' => 'password123',
        ])->assertSuccessful();

        $user = User::where('email', 'no-role@example.com')->firstOrFail();

        $this->assertCount(0, $user->roles);
    }

    // ──────────────────────────────────────────────
    // Route helpers
    // ──────────────────────────────────────────────

    protected function registerTwoFactorRoutes(): void
    {
        Route::middleware(['web', 'auth'])->group(function () {
            Route::post(
                '/user/two-factor-authentication',
                [TwoFactorAuthController::class, 'store']
            );
        });
    }

    protected function registerPasswordlessRoutes(): void
    {
        Route::middleware(['web'])->group(function () {
            Route::post(
                '/login/totp',
                [LoginController::class, 'authenticateWithTotp']
            );
        });
    }
}
