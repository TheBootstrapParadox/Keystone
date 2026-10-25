<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Support\UserModel;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserModelResolutionTest extends TestCase
{
    #[Test]
    public function keystones_own_setting_wins_over_the_auth_provider()
    {
        config(['keystone.user.model' => User::class, 'auth.providers.users.model' => 'Domain\Accounts\Member']);

        $this->assertSame(User::class, UserModel::className());
    }

    #[Test]
    public function the_auth_provider_model_is_used_when_keystone_names_none()
    {
        config(['keystone.user.model' => null, 'auth.providers.users.model' => User::class]);

        $this->assertSame(User::class, UserModel::className());
    }

    #[Test]
    public function an_unconfigured_user_model_names_both_config_keys()
    {
        config(['keystone.user.model' => null, 'auth.providers.users.model' => null]);

        try {
            UserModel::className();
            $this->fail('Expected a LogicException.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('keystone.user.model', $e->getMessage());
            $this->assertStringContainsString('auth.providers.users.model', $e->getMessage());
        }
    }

    #[Test]
    public function commands_fail_clearly_without_a_user_model()
    {
        config(['keystone.user.model' => null, 'auth.providers.users.model' => null]);

        $this->expectException(LogicException::class);

        $this->artisan('keystone:assign-role', ['user' => '1', 'role' => ['editor']]);
    }
}
