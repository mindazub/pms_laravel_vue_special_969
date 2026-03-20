<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_demo_workspace_for_fixed_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        /** @var User $admin */
        $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        /** @var User $ceo */
        $ceo = User::query()->where('email', 'ceo@ceo.com')->firstOrFail();
        /** @var User $manager */
        $manager = User::query()->where('email', 'manager@manager.com')->firstOrFail();
        /** @var User $hr */
        $hr = User::query()->where('email', 'hr@hr.com')->firstOrFail();
        /** @var User $user */
        $user = User::query()->where('email', 'user@user.com')->firstOrFail();

        $this->assertTrue($admin->hasRole(User::ROLE_ADMIN));
        $this->assertTrue($ceo->hasRole(User::ROLE_CEO));
        $this->assertTrue($manager->hasRole(User::ROLE_MANAGER));
        $this->assertTrue($hr->hasRole(User::ROLE_HR));
        $this->assertTrue($user->hasRole(User::ROLE_USER));

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);

        /** @var Team $team */
        $team = Team::query()->where('slug', 'product-delivery')->firstOrFail();

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $manager->id,
            'role' => User::ROLE_MANAGER,
        ]);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $hr->id,
            'role' => User::ROLE_HR,
        ]);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => User::ROLE_USER,
        ]);

        /** @var Project $project */
        $project = Project::query()
            ->where('name', 'Northwind Platform Launch')
            ->firstOrFail();

        $this->assertSame($team->id, $project->team_id);
        $this->assertSame($manager->id, $project->project_manager_id);
        $this->assertSame('Northwind Labs', $project->customer?->name);
        $this->assertSame('Team', $project->mentions[1]['type']);
        $this->assertSame($team->id, $project->mentions[1]['id']);

        /** @var Note $note */
        $note = Note::query()
            ->where('project_id', $project->id)
            ->where('title', 'Ship QA fixes')
            ->firstOrFail();

        $this->assertSame(Note::STATUS_IN_PROGRESS, $note->status);
        $this->assertSame(50, $note->progress);
        $this->assertSame($team->id, $note->team_id);
        $this->assertSame(
            [$manager->id, $user->id],
            $note->assignees()->orderBy('users.id')->pluck('users.id')->all()
        );
        $this->assertSame('User', $note->mentions[0]['type']);
        $this->assertSame($manager->id, $note->mentions[0]['id']);
    }
}
