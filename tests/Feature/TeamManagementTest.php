<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_team_index_returns_visible_team_members_for_manager(): void
    {
        /** @var User $manager */
        $manager = User::factory()->createOne();
        /** @var User $member */
        $member = User::factory()->createOne();

        $customer = Customer::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'created_by' => $manager->id,
        ]);

        $team = Team::query()->create([
            'name' => 'Platform',
            'slug' => 'platform',
            'customer_id' => $customer->id,
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $team->users()->attach([
            $manager->id => ['role' => User::ROLE_MANAGER],
            $member->id => ['role' => User::ROLE_USER],
        ]);

        $response = $this
            ->actingAs($manager)
            ->getJson(route('teams.index'));

        $response
            ->assertOk()
            ->assertJsonPath('0.id', $team->id)
            ->assertJsonPath('0.manager.id', $manager->id)
            ->assertJsonPath('0.customer.id', $customer->id)
            ->assertJsonCount(2, '0.users');
    }

    public function test_team_manager_can_sync_team_members_and_roles(): void
    {
        /** @var User $manager */
        $manager = User::factory()->createOne();
        /** @var User $existingMember */
        $existingMember = User::factory()->createOne();
        /** @var User $newMember */
        $newMember = User::factory()->createOne();

        $team = Team::query()->create([
            'name' => 'Operations',
            'slug' => 'operations',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $team->users()->attach([
            $manager->id => ['role' => User::ROLE_MANAGER],
            $existingMember->id => ['role' => User::ROLE_USER],
        ]);

        $response = $this
            ->actingAs($manager)
            ->patchJson(route('teams.members.sync', $team), [
                'members' => [
                    ['id' => $existingMember->id, 'role' => User::ROLE_HR],
                    ['id' => $newMember->id, 'role' => User::ROLE_USER],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonCount(3, 'users');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $manager->id,
            'role' => User::ROLE_MANAGER,
        ]);

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $existingMember->id,
            'role' => User::ROLE_HR,
        ]);

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $newMember->id,
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_admin_can_create_team(): void
    {
        /** @var User $admin */
        $admin = User::factory()->createOne();
        $admin->syncRoles([User::ROLE_ADMIN]);

        /** @var User $manager */
        $manager = User::factory()->createOne();

        $response = $this
            ->actingAs($admin)
            ->postJson(route('teams.store'), [
                'name' => 'Program Delivery',
                'description' => 'Delivery team for enterprise rollout.',
                'manager_id' => $manager->id,
                'member_ids' => [$manager->id],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('name', 'Program Delivery');

        $teamId = (int) $response->json('id');

        $this->assertDatabaseHas('teams', [
            'id' => $teamId,
            'name' => 'Program Delivery',
            'manager_id' => $manager->id,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('team_user', [
            'team_id' => $teamId,
            'user_id' => $manager->id,
            'role' => User::ROLE_MANAGER,
        ]);
    }

    public function test_regular_user_cannot_create_team(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();
        $user->syncRoles([User::ROLE_USER]);

        $this
            ->actingAs($user)
            ->postJson(route('teams.store'), [
                'name' => 'Forbidden Team',
            ])
            ->assertForbidden();
    }

    public function test_only_team_manager_can_update_team_when_not_admin_or_ceo(): void
    {
        /** @var User $manager */
        $manager = User::factory()->createOne();
        /** @var User $nonManager */
        $nonManager = User::factory()->createOne();

        $team = Team::query()->create([
            'name' => 'Support',
            'slug' => 'support',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $team->users()->attach([
            $manager->id => ['role' => User::ROLE_MANAGER],
            $nonManager->id => ['role' => User::ROLE_USER],
        ]);

        $this
            ->actingAs($nonManager)
            ->putJson(route('teams.update', $team), [
                'name' => 'Support Updated',
            ])
            ->assertForbidden();

        $this
            ->actingAs($manager)
            ->putJson(route('teams.update', $team), [
                'name' => 'Support Updated',
                'description' => 'Updated by manager.',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Support Updated');

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Support Updated',
        ]);
    }

    public function test_only_team_manager_can_delete_team_when_not_admin_or_ceo(): void
    {
        /** @var User $manager */
        $manager = User::factory()->createOne();
        /** @var User $nonManager */
        $nonManager = User::factory()->createOne();

        $team = Team::query()->create([
            'name' => 'Delivery',
            'slug' => 'delivery',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $team->users()->attach([
            $manager->id => ['role' => User::ROLE_MANAGER],
            $nonManager->id => ['role' => User::ROLE_USER],
        ]);

        $this
            ->actingAs($nonManager)
            ->deleteJson(route('teams.destroy', $team))
            ->assertForbidden();

        $this
            ->actingAs($manager)
            ->deleteJson(route('teams.destroy', $team))
            ->assertOk();

        $this->assertDatabaseMissing('teams', [
            'id' => $team->id,
        ]);
    }
}
