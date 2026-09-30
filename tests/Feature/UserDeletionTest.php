<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\SoftDeletingUser;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        KeystoneRole::create(['name' => 'editor']);
        KeystonePermission::create(['name' => 'publish-posts']);
    }

    #[Test]
    public function deleting_a_user_removes_their_assignments()
    {
        $user = User::factory()->create();
        $user->assignRole('editor')->givePermissionTo('publish-posts');
        $other = User::factory()->create();
        $other->assignRole('editor');

        $user->delete();

        $this->assertSame(0, $this->assignmentCount($user));
        $this->assertSame(1, $this->assignmentCount($other));
    }

    #[Test]
    #[Group('ddl')]
    public function a_soft_deleted_user_keeps_assignments_until_force_deleted()
    {
        Schema::table('users', fn (Blueprint $table) => $table->softDeletes());
        $user = SoftDeletingUser::create(['name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'x']);
        $user->assignRole('editor');

        $user->delete();
        $user->restore();

        $this->assertTrue($user->fresh()->hasRole('editor'));

        $user->forceDelete();

        $this->assertSame(0, $this->assignmentCount($user));
    }

    private function assignmentCount(User $user): int
    {
        return collect(['model_has_roles', 'model_has_permissions'])->sum(
            fn ($table) => DB::table($table)
                ->where('model_type', $user->getMorphClass())
                ->where('model_id', $user->getKey())
                ->count()
        );
    }
}
