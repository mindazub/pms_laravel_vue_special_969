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

    public function test_people_page_renders_visible_people_data(): void
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
                ->has('teams', 1)
                ->has('customers', 1)
                ->has('users', 2)
                ->where('teams.0.id', $team->id)
                ->where('customers.0.id', $customer->id)
            );
    }
}
