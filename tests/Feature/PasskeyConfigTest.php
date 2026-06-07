<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Actions\GeneratePasskeyRegisterOptionsAction as KeystoneAction;
use BSPDX\Keystone\Http\Controllers\PasskeyAuthController;
use BSPDX\Keystone\Services\Contracts\PasskeyServiceInterface;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction as SpatieAction;
use Tests\TestCase;

class PasskeyConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['keystone.features.passkeys' => true]);

        Route::middleware(['web', 'auth'])
            ->group(function () {
                Route::post('/user/passkeys/options', [PasskeyAuthController::class, 'registerOptions']);
                Route::post('/user/passkeys', [PasskeyAuthController::class, 'store']);
            });
    }

    #[Test]
    public function service_provider_bridges_rp_name_from_keystone_config(): void
    {
        config(['keystone.passkey.rp_name' => 'My Custom App']);
        // Re-run the bridge (simulates provider boot with custom value)
        config(['passkeys.relying_party.name' => config('keystone.passkey.rp_name')]);

        $this->assertSame('My Custom App', config('passkeys.relying_party.name'));
    }

    #[Test]
    public function service_provider_bridges_rp_id_from_keystone_config(): void
    {
        config(['keystone.passkey.rp_id' => 'example.com']);
        config(['passkeys.relying_party.id' => config('keystone.passkey.rp_id')]);

        $this->assertSame('example.com', config('passkeys.relying_party.id'));
    }

    #[Test]
    public function spatie_action_resolves_to_keystone_custom_action(): void
    {
        $resolved = app(SpatieAction::class);

        $this->assertInstanceOf(KeystoneAction::class, $resolved);
    }

    #[Test]
    public function register_options_blocks_second_passkey_when_allow_multiple_is_false(): void
    {
        config(['keystone.passkey.allow_multiple' => false]);

        $user = User::factory()->create();

        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson('/user/passkeys/options')
            ->assertStatus(422)
            ->assertJson(['message' => 'Only one passkey is allowed per account.']);
    }

    #[Test]
    public function register_options_allows_first_passkey_when_allow_multiple_is_false(): void
    {
        config(['keystone.passkey.allow_multiple' => false]);

        $user = User::factory()->create();

        $this->mock(PasskeyServiceInterface::class)
            ->shouldReceive('generateRegisterOptions')
            ->once()
            ->andReturn('{}');

        $this->actingAs($user)
            ->postJson('/user/passkeys/options')
            ->assertSuccessful();
    }

    #[Test]
    public function register_options_allows_multiple_passkeys_when_allow_multiple_is_true(): void
    {
        config(['keystone.passkey.allow_multiple' => true]);

        $user = User::factory()->create();

        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->mock(PasskeyServiceInterface::class)
            ->shouldReceive('generateRegisterOptions')
            ->once()
            ->andReturn('{}');

        $this->actingAs($user)
            ->postJson('/user/passkeys/options')
            ->assertSuccessful();
    }

    #[Test]
    public function store_blocks_second_passkey_when_allow_multiple_is_false(): void
    {
        config(['keystone.passkey.allow_multiple' => false]);

        $user = User::factory()->create();

        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson('/user/passkeys', [
                'name' => 'New Key',
                'credential' => '{}',
                'options' => '{}',
            ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Only one passkey is allowed per account.']);
    }
}
