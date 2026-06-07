<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Http\Controllers\AccountDeletionController;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->group(function () {
            Route::delete('/user/account', [AccountDeletionController::class, 'destroy']);
        });
    }

    #[Test]
    public function destroy_returns_404_when_feature_is_disabled(): void
    {
        config(['keystone.features.account_deletion' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/user/account')
            ->assertNotFound();
    }

    #[Test]
    public function destroy_deletes_the_user_record(): void
    {
        config(['keystone.features.account_deletion' => true]);

        $user = User::factory()->create();
        $userId = $user->id;

        $this->actingAs($user)
            ->deleteJson('/user/account')
            ->assertOk()
            ->assertJson(['message' => 'Account deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    #[Test]
    public function destroy_revokes_sanctum_tokens(): void
    {
        config(['keystone.features.account_deletion' => true]);

        $user = User::factory()->create();
        $user->createToken('test-token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson('/user/account')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    #[Test]
    public function destroy_returns_redirect_for_web_requests(): void
    {
        config(['keystone.features.account_deletion' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/user/account')
            ->assertRedirect('/');
    }
}
