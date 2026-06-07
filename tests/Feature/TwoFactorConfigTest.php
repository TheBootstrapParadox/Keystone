<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Controllers\TwoFactorAuthController;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TwoFactorConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.two_factor' => true]);

        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('/user/confirmed-two-factor-authentication', [TwoFactorAuthController::class, 'confirm']);
        });
    }

    // ──────────────────────────────────────────────
    // qr_code_size
    // ──────────────────────────────────────────────

    #[Test]
    public function twoFactorQrCodeSvg_uses_qr_code_size_from_keystone_config(): void
    {
        config(['keystone.two_factor.qr_code_size' => 300]);

        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt(app('pragmarx.google2fa')->generateSecretKey()),
        ])->save();

        $svg = $user->twoFactorQrCodeSvg();

        $this->assertStringContainsString('width="300"', $svg);
        $this->assertStringContainsString('height="300"', $svg);
    }

    #[Test]
    public function twoFactorQrCodeSvg_defaults_to_200_when_config_not_set(): void
    {
        config(['keystone.two_factor.qr_code_size' => 200]);

        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt(app('pragmarx.google2fa')->generateSecretKey()),
        ])->save();

        $svg = $user->twoFactorQrCodeSvg();

        $this->assertStringContainsString('width="200"', $svg);
    }

    #[Test]
    public function twoFactorQrCodeSvg_responds_to_config_change(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt(app('pragmarx.google2fa')->generateSecretKey()),
        ])->save();

        config(['keystone.two_factor.qr_code_size' => 150]);
        $svgSmall = $user->twoFactorQrCodeSvg();

        config(['keystone.two_factor.qr_code_size' => 400]);
        $svgLarge = $user->twoFactorQrCodeSvg();

        $this->assertStringContainsString('width="150"', $svgSmall);
        $this->assertStringContainsString('width="400"', $svgLarge);
    }

    // ──────────────────────────────────────────────
    // window
    // ──────────────────────────────────────────────

    #[Test]
    public function confirm_applies_window_from_keystone_config(): void
    {
        config(['keystone.two_factor.window' => 3]);

        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('setWindow')->once()->with(3);
        $google2fa->shouldReceive('verifyKey')->once()->andReturn(false);
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->postJson('/user/confirmed-two-factor-authentication', ['code' => '123456'])
            ->assertStatus(422);
    }

    #[Test]
    public function confirm_uses_window_1_by_default(): void
    {
        config(['keystone.two_factor.window' => 1]);

        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('setWindow')->once()->with(1);
        $google2fa->shouldReceive('verifyKey')->once()->andReturn(false);
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->postJson('/user/confirmed-two-factor-authentication', ['code' => '123456'])
            ->assertStatus(422);
    }
}
