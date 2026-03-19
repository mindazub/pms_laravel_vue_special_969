<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Project;
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
        $project = Project::factory()->for($user)->createOne([
            'name' => 'Website redesign',
        ]);

        Note::factory()->for($user)->for($project)->createOne([
            'title' => 'Define sitemap',
            'content' => 'Outline the pages and hierarchy.',
            'status' => Note::STATUS_TODO,
            'progress' => 0,
        ]);

        Note::factory()->for($user)->for($project)->createOne([
            'title' => 'Build wireframes',
            'content' => 'Create low fidelity layout drafts.',
            'status' => Note::STATUS_IN_PROGRESS,
            'progress' => 50,
        ]);

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
                ->where('selectedProject.notes_count', 3)
                ->where('selectedProject.done_notes_count', 1)
                ->where('selectedProject.completion_percentage', 33)
                ->where('statuses', Note::STATUSES)
                ->has('notes', 3)
                ->where('progressSteps', [0, 25, 50, 75, 100])
            );
    }
}
