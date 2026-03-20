<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeoplePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_page_requires_authentication(): void
    {
        $this->get(route('people.index'))->assertRedirect(route('login'));
    }

    public function test_people_page_renders_hr_overview_data(): void
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

        $response = $this
            ->actingAs($manager)
            ->get(route('people.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/Index')
                ->where('metrics.total_users', 2)
                ->where('metrics.total_teams', 1)
                ->where('metrics.total_customers', 1)
                ->where('metrics.coverage_rate', 100)
                ->has('largestTeams', 1)
                ->where('largestTeams.0.id', $team->id)
                ->has('recentCustomers', 1)
                ->where('recentCustomers.0.id', $customer->id)
            );
    }
}
