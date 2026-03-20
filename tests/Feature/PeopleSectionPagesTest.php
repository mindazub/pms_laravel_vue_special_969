<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeopleSectionPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_people_teams_page_renders_visible_teams_data(): void
    {
        /** @var User $manager */
        $manager = User::factory()->createOne();
        /** @var User $member */
        $member = User::factory()->createOne();

        $customer = Customer::query()->create([
            'name' => 'Visible Customer',
            'slug' => 'visible-customer',
            'created_by' => $manager->id,
        ]);

        $team = Team::query()->create([
            'name' => 'Visible Team',
            'slug' => 'visible-team',
            'customer_id' => $customer->id,
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $team->users()->attach([
            $manager->id => ['role' => User::ROLE_MANAGER],
            $member->id => ['role' => User::ROLE_USER],
        ]);

        $this
            ->actingAs($manager)
            ->get(route('people.teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/Teams')
                ->has('teams', 1)
                ->has('customers', 1)
                ->has('users', 2)
                ->where('teams.0.id', $team->id)
            );
    }

    public function test_people_customers_page_renders_visible_customers_data(): void
    {
        /** @var User $manager */
        $manager = User::factory()->createOne();

        $customer = Customer::query()->create([
            'name' => 'Visible Customer',
            'slug' => 'visible-customer',
            'created_by' => $manager->id,
        ]);

        $team = Team::query()->create([
            'name' => 'Visible Team',
            'slug' => 'visible-team',
            'customer_id' => $customer->id,
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $team->users()->attach($manager->id, ['role' => User::ROLE_MANAGER]);

        $this
            ->actingAs($manager)
            ->get(route('people.customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/Customers')
                ->has('customers', 1)
                ->where('customers.0.id', $customer->id)
            );
    }

    public function test_people_users_page_renders_users_with_team_data(): void
    {
        /** @var User $admin */
        $admin = User::factory()->createOne([
            'name' => 'Zed Admin',
        ]);
        $admin->syncRoles([User::ROLE_ADMIN]);

        /** @var User $member */
        $member = User::factory()->createOne([
            'name' => 'Aaa Member',
        ]);
        $member->syncRoles([User::ROLE_USER]);

        $team = Team::query()->create([
            'name' => 'Visible Team',
            'slug' => 'visible-team',
            'manager_id' => $admin->id,
            'created_by' => $admin->id,
        ]);

        $team->users()->attach([
            $admin->id => ['role' => User::ROLE_MANAGER],
            $member->id => ['role' => User::ROLE_USER],
        ]);

        $this
            ->actingAs($admin)
            ->get(route('people.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/Users')
                ->where('canManageRoles', true)
                ->has('users', fn (Assert $users) => $users
                    ->where('0.name', $member->name)
                    ->where('0.team_names.0', 'Visible Team')
                    ->where('0.team_count', 1)
                    ->etc()
                )
            );
    }
}
