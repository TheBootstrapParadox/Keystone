<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Controllers\PasskeyAuthController;
use BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasskeyTwoFactorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.passkey_2fa' => true]);

        Route::middleware(['web', 'auth', 'keystone.feature:passkey_2fa'])->group(function () {
            Route::get('/passkey/2fa/challenge', [PasskeyAuthController::class, 'twofaChallengeView'])
                ->name('passkeys.2fa.challenge');
            Route::post('/passkey/2fa/options', [PasskeyAuthController::class, 'twofaChallengeOptions'])
                ->name('passkeys.2fa.options');
            Route::post('/passkey/2fa/verify', [PasskeyAuthController::class, 'twofaVerify'])
                ->name('passkeys.2fa.verify');
        });

        Route::middleware(['web', 'auth', 'passkey-2fa'])
            ->get('/protected', fn () => response()->json(['ok' => true]))
            ->name('test.passkey-2fa.protected');
    }

    // ──────────────────────────────────────────────
    // RequirePasskey2FA middleware
    // ──────────────────────────────────────────────

    #[Test]
    public function middleware_passes_when_feature_is_disabled(): void
    {
        config(['keystone.features.passkey_2fa' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/protected')
            ->assertOk();
    }

    #[Test]
    public function middleware_passes_when_user_has_no_passkeys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/protected')
            ->assertOk();
    }

    #[Test]
    public function middleware_passes_when_session_flag_is_set(): void
    {
        $user = User::factory()->create();

        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['auth.passkey_2fa_verified_at' => now()])
            ->getJson('/protected')
            ->assertOk();
    }

    #[Test]
    public function middleware_returns_423_for_json_when_passkey_2fa_required(): void
    {
        $user = User::factory()->create();

        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson('/protected')
            ->assertStatus(423)
            ->assertJson(['message' => 'Passkey verification required.']);
    }

    #[Test]
    public function middleware_redirects_web_requests_to_challenge(): void
    {
        $user = User::factory()->create();

        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/protected')
            ->assertRedirect(route('passkeys.2fa.challenge'));
    }

    // ──────────────────────────────────────────────
    // twofaVerify endpoint
    // ──────────────────────────────────────────────

    #[Test]
    public function twofa_verify_returns_404_when_feature_disabled(): void
    {
        config(['keystone.features.passkey_2fa' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['passkey_2fa_options' => '{}'])
            ->postJson('/passkey/2fa/verify', ['credential' => '{}'])
            ->assertNotFound();
    }

    #[Test]
    public function twofa_verify_requires_session_challenge(): void
    {
        $user = User::factory()->create();

        // No session challenge — should reject without calling the service.
        $this->actingAs($user)
            ->postJson('/passkey/2fa/verify', ['credential' => '{}'])
            ->assertStatus(422)
            ->assertJson(['message' => 'No active challenge. Please request a new one.']);
    }

    #[Test]
    public function twofa_verify_rejects_passkey_owned_by_different_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $mockPasskey = new \BSPDX\Keystone\Models\Passkey;
        $mockPasskey->authenticatable_id = $otherUser->id; // belongs to someone else

        $this->mock(PasskeyServiceInterface::class)
            ->shouldReceive('findPasskeyToAuthenticate')
            ->once()
            ->andReturn($mockPasskey);

        $this->actingAs($user)
            ->withSession(['passkey_2fa_options' => '{}'])
            ->postJson('/passkey/2fa/verify', ['credential' => '{}'])
            ->assertStatus(401);
    }

    #[Test]
    public function twofa_verify_sets_session_flag_on_success(): void
    {
        $user = User::factory()->create();

        $mockPasskey = new \BSPDX\Keystone\Models\Passkey;
        $mockPasskey->authenticatable_id = $user->id;

        $this->mock(PasskeyServiceInterface::class)
            ->shouldReceive('findPasskeyToAuthenticate')
            ->once()
            ->andReturn($mockPasskey);

        $this->actingAs($user)
            ->withSession(['passkey_2fa_options' => '{}'])
            ->postJson('/passkey/2fa/verify', ['credential' => '{}'])
            ->assertOk()
            ->assertSessionHas('auth.passkey_2fa_verified_at');
    }

    #[Test]
    public function twofa_verify_consumes_challenge_preventing_replay(): void
    {
        $user = User::factory()->create();

        $mockPasskey = new \BSPDX\Keystone\Models\Passkey;
        $mockPasskey->authenticatable_id = $user->id;

        $this->mock(PasskeyServiceInterface::class)
            ->shouldReceive('findPasskeyToAuthenticate')
            ->once()
            ->andReturn($mockPasskey);

        // First call succeeds and sets the flag.
        $this->actingAs($user)
            ->withSession(['passkey_2fa_options' => '{}'])
            ->postJson('/passkey/2fa/verify', ['credential' => '{}'])
            ->assertOk();

        // Subsequent call with no session challenge is rejected.
        $this->actingAs($user)
            ->postJson('/passkey/2fa/verify', ['credential' => '{}'])
            ->assertStatus(422);
    }

    #[Test]
    public function twofa_verify_returns_401_when_passkey_invalid(): void
    {
        $user = User::factory()->create();

        $this->mock(PasskeyServiceInterface::class)
            ->shouldReceive('findPasskeyToAuthenticate')
            ->once()
            ->andReturn(null);

        $this->actingAs($user)
            ->withSession(['passkey_2fa_options' => '{}'])
            ->postJson('/passkey/2fa/verify', ['credential' => '{}'])
            ->assertStatus(401);
    }
}
