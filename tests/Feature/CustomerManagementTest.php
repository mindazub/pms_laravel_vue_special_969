<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_customer(): void
    {
        /** @var User $admin */
        $admin = User::factory()->createOne();
        $admin->syncRoles([User::ROLE_ADMIN]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('customers.store'), [
                'name' => 'Aperture Systems',
                'description' => 'External customer for seeded enterprise workload.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('name', 'Aperture Systems');

        $this->assertDatabaseHas('customers', [
            'name' => 'Aperture Systems',
            'created_by' => $admin->id,
        ]);
    }

    public function test_ceo_can_update_and_delete_customer(): void
    {
        /** @var User $ceo */
        $ceo = User::factory()->createOne();
        $ceo->syncRoles([User::ROLE_CEO]);

        $customer = Customer::query()->create([
            'name' => 'Legacy Name',
            'slug' => 'legacy-name',
            'created_by' => $ceo->id,
        ]);

        $this
            ->actingAs($ceo)
            ->putJson(route('customers.update', $customer), [
                'name' => 'Renamed Customer',
                'description' => 'Updated by CEO.',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed Customer');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Renamed Customer',
        ]);

        $this
            ->actingAs($ceo)
            ->deleteJson(route('customers.destroy', $customer))
            ->assertOk();

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }

    public function test_regular_user_cannot_manage_customers(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();
        $user->syncRoles([User::ROLE_USER]);

        $customer = Customer::query()->create([
            'name' => 'Restricted Customer',
            'slug' => 'restricted-customer',
            'created_by' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->postJson(route('customers.store'), [
                'name' => 'Should Not Create',
            ])
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->putJson(route('customers.update', $customer), [
                'name' => 'Should Not Update',
            ])
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->deleteJson(route('customers.destroy', $customer))
            ->assertForbidden();
    }

    public function test_team_member_only_sees_customers_linked_to_their_teams(): void
    {
        /** @var User $member */
        $member = User::factory()->createOne();
        $member->syncRoles([User::ROLE_USER]);

        /** @var User $manager */
        $manager = User::factory()->createOne();

        $visibleCustomer = Customer::query()->create([
            'name' => 'Visible Customer',
            'slug' => 'visible-customer',
            'created_by' => $manager->id,
        ]);

        $hiddenCustomer = Customer::query()->create([
            'name' => 'Hidden Customer',
            'slug' => 'hidden-customer',
            'created_by' => $manager->id,
        ]);

        $visibleTeam = Team::query()->create([
            'name' => 'Visible Team',
            'slug' => 'visible-team',
            'customer_id' => $visibleCustomer->id,
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        Team::query()->create([
            'name' => 'Hidden Team',
            'slug' => 'hidden-team',
            'customer_id' => $hiddenCustomer->id,
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $visibleTeam->users()->attach($member->id, ['role' => User::ROLE_USER]);

        $response = $this
            ->actingAs($member)
            ->getJson(route('customers.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Visible Customer'])
            ->assertJsonMissing(['name' => 'Hidden Customer']);
    }
}
