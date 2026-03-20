<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Note;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectsBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_board_requires_authentication(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    public function test_projects_board_returns_selected_project_and_notes(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();
        $customer = Customer::query()->create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'created_by' => $user->id,
        ]);
        $team = Team::query()->create([
            'name' => 'Design',
            'slug' => 'design',
            'customer_id' => $customer->id,
            'manager_id' => $user->id,
            'created_by' => $user->id,
        ]);
        $team->users()->attach($user->id, ['role' => User::ROLE_MANAGER]);

        $assignee = User::factory()->createOne();
        $project = Project::factory()->for($user)->createOne([
            'name' => 'Website redesign',
            'team_id' => $team->id,
            'customer_id' => $customer->id,
            'project_manager_id' => $user->id,
            'mentions' => [
                ['type' => 'Team', 'id' => $team->id, 'name' => $team->name],
                ['type' => 'Customer', 'id' => $customer->id, 'name' => $customer->name],
            ],
        ]);

        Note::factory()->for($user)->for($project)->createOne([
            'title' => 'Define sitemap',
            'content' => 'Outline the pages and hierarchy.',
            'status' => Note::STATUS_TODO,
            'progress' => 0,
        ]);

        $inProgressNote = Note::factory()->for($user)->for($project)->createOne([
            'title' => 'Build wireframes',
            'content' => 'Create low fidelity layout drafts.',
            'status' => Note::STATUS_IN_PROGRESS,
            'progress' => 50,
            'team_id' => $team->id,
            'mentions' => [
                ['type' => 'User', 'id' => $assignee->id, 'name' => $assignee->name],
            ],
        ]);
        $inProgressNote->assignees()->attach($assignee->id);

        Note::factory()->for($user)->for($project)->createOne([
            'title' => 'Ship handoff',
            'content' => 'Deliver the final assets package.',
            'status' => Note::STATUS_DONE,
            'progress' => 100,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('projects.index', ['project' => $project->id]));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('selectedProject.id', $project->id)
                ->where('selectedProject.name', 'Website redesign')
                ->where('selectedProject.team_id', $team->id)
                ->where('selectedProject.team_name', 'Design')
                ->where('selectedProject.customer_id', $customer->id)
                ->where('selectedProject.customer_name', 'Acme Corp')
                ->where('selectedProject.project_manager_id', $user->id)
                ->where('selectedProject.project_manager_name', $user->name)
                ->where('selectedProject.notes_count', 3)
                ->where('selectedProject.done_notes_count', 1)
                ->where('selectedProject.completion_percentage', 33)
                ->where('statuses', Note::STATUSES)
                ->has('notes', 3)
                ->where('notes.1.id', $inProgressNote->id)
                ->where('notes.1.assignee_ids', [$assignee->id])
                ->where('notes.1.assignee_names', [$assignee->name])
                ->where('notes.1.mentions.0.type', 'User')
                ->where('notes.1.mentions.0.id', $assignee->id)
                ->where('notes.1.mentions.0.name', $assignee->name)
                ->has('teams', 1)
                ->has('customers', 1)
                ->has('users', 2)
                ->where('progressSteps', [0, 25, 50, 75, 100])
            );
    }

    public function test_projects_board_shows_team_project_to_team_member(): void
    {
        /** @var User $owner */
        $owner = User::factory()->createOne();
        /** @var User $member */
        $member = User::factory()->createOne();

        $customer = Customer::query()->create([
            'name' => 'Globex',
            'slug' => 'globex',
            'created_by' => $owner->id,
        ]);

        $team = Team::query()->create([
            'name' => 'Operations',
            'slug' => 'operations',
            'customer_id' => $customer->id,
            'manager_id' => $owner->id,
            'created_by' => $owner->id,
        ]);

        $team->users()->attach([
            $owner->id => ['role' => User::ROLE_MANAGER],
            $member->id => ['role' => User::ROLE_USER],
        ]);

        $project = Project::factory()->for($owner)->createOne([
            'name' => 'Ops rollout',
            'team_id' => $team->id,
            'customer_id' => $customer->id,
            'project_manager_id' => $owner->id,
        ]);

        $response = $this
            ->actingAs($member)
            ->get(route('projects.index', ['project' => $project->id]));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('selectedProject.id', $project->id)
                ->where('selectedProject.team_name', 'Operations')
                ->where('projects.data.0.id', $project->id)
            );
    }
}
