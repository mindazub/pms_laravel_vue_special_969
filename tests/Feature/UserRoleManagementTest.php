<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_list_users_with_roles(): void
    {
        /** @var User $admin */
        $admin = User::factory()->createOne();
        $admin->syncRoles([User::ROLE_ADMIN]);

        /** @var User $manager */
        $manager = User::factory()->createOne();
        $manager->syncRoles([User::ROLE_MANAGER]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('users.roles.index'));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'email' => $manager->email,
            ])
            ->assertJsonFragment([
                'role_names' => [User::ROLE_MANAGER],
            ]);
    }

    public function test_ceo_can_update_user_role(): void
    {
        /** @var User $ceo */
        $ceo = User::factory()->createOne();
        $ceo->syncRoles([User::ROLE_CEO]);

        /** @var User $target */
        $target = User::factory()->createOne();
        $target->syncRoles([User::ROLE_USER]);

        $response = $this
            ->actingAs($ceo)
            ->putJson(route('users.roles.update', $target), [
                'role' => User::ROLE_HR,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('role_names.0', User::ROLE_HR);

        $this->assertSame([User::ROLE_HR], $target->fresh()->getRoleNames()->values()->all());
    }

    public function test_regular_user_cannot_manage_roles(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();
        $user->syncRoles([User::ROLE_USER]);

        /** @var User $target */
        $target = User::factory()->createOne();

        $this
            ->actingAs($user)
            ->putJson(route('users.roles.update', $target), [
                'role' => User::ROLE_MANAGER,
            ])
            ->assertForbidden();
    }
}
