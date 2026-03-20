<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Note;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $ceo = User::query()->where('email', 'ceo@ceo.com')->firstOrFail();
        $manager = User::query()->where('email', 'manager@manager.com')->firstOrFail();
        $hr = User::query()->where('email', 'hr@hr.com')->firstOrFail();
        $user = User::query()->where('email', 'user@user.com')->firstOrFail();

        $northwind = Customer::query()->updateOrCreate(
            ['slug' => 'northwind-labs'],
            [
                'name' => 'Northwind Labs',
                'description' => 'Primary product customer used across the seeded launch board.',
                'created_by' => $ceo->id,
            ]
        );

        $contoso = Customer::query()->updateOrCreate(
            ['slug' => 'contoso-people'],
            [
                'name' => 'Contoso People',
                'description' => 'Internal people operations customer used for onboarding and HR planning.',
                'created_by' => $admin->id,
            ]
        );

        $productDelivery = Team::query()->updateOrCreate(
            ['slug' => 'product-delivery'],
            [
                'name' => 'Product Delivery',
                'description' => 'Cross-functional delivery team responsible for the launch pipeline.',
                'customer_id' => $northwind->id,
                'manager_id' => $manager->id,
                'created_by' => $admin->id,
            ]
        );

        $peopleOperations = Team::query()->updateOrCreate(
            ['slug' => 'people-operations'],
            [
                'name' => 'People Operations',
                'description' => 'Operational team coordinating onboarding, enablement, and internal comms.',
                'customer_id' => $contoso->id,
                'manager_id' => $manager->id,
                'created_by' => $ceo->id,
            ]
        );

        $productDelivery->users()->sync([
            $manager->id => ['role' => User::ROLE_MANAGER],
            $hr->id => ['role' => User::ROLE_HR],
            $user->id => ['role' => User::ROLE_USER],
        ]);

        $peopleOperations->users()->sync([
            $manager->id => ['role' => User::ROLE_MANAGER],
            $hr->id => ['role' => User::ROLE_HR],
            $user->id => ['role' => User::ROLE_USER],
        ]);

        $platformLaunch = Project::query()->updateOrCreate(
            [
                'user_id' => $manager->id,
                'name' => 'Northwind Platform Launch',
            ],
            [
                'description' => 'Release coordination project covering QA, rollout, and customer handoff.',
                'clipboard_text' => 'Coordinate release blockers, rollout notes, and launch ownership.',
                'attachments' => [],
                'mentions' => [
                    ['type' => 'Customer', 'id' => $northwind->id, 'name' => $northwind->name],
                    ['type' => 'Team', 'id' => $productDelivery->id, 'name' => $productDelivery->name],
                    ['type' => 'User', 'id' => $user->id, 'name' => $user->name],
                ],
                'team_id' => $productDelivery->id,
                'customer_id' => $northwind->id,
                'project_manager_id' => $manager->id,
            ]
        );

        $onboardingRefresh = Project::query()->updateOrCreate(
            [
                'user_id' => $hr->id,
                'name' => 'Contoso Onboarding Refresh',
            ],
            [
                'description' => 'Refresh onboarding materials, enablement sessions, and people ops follow-up.',
                'clipboard_text' => 'Capture handbook updates, welcome session timing, and enablement sign-offs.',
                'attachments' => [],
                'mentions' => [
                    ['type' => 'Customer', 'id' => $contoso->id, 'name' => $contoso->name],
                    ['type' => 'Team', 'id' => $peopleOperations->id, 'name' => $peopleOperations->name],
                    ['type' => 'User', 'id' => $hr->id, 'name' => $hr->name],
                ],
                'team_id' => $peopleOperations->id,
                'customer_id' => $contoso->id,
                'project_manager_id' => $manager->id,
            ]
        );

        $launchChecklist = Note::query()->updateOrCreate(
            [
                'project_id' => $platformLaunch->id,
                'title' => 'Finalize launch checklist',
            ],
            [
                'user_id' => $manager->id,
                'team_id' => $productDelivery->id,
                'content' => 'Confirm ownership, release gating, and final verification items before launch day.',
                'clipboard_text' => 'Owner matrix, launch gates, last-mile verification.',
                'status' => Note::STATUS_TODO,
                'progress' => 0,
                'attachments' => [],
                'mentions' => [
                    ['type' => 'Team', 'id' => $productDelivery->id, 'name' => $productDelivery->name],
                    ['type' => 'Customer', 'id' => $northwind->id, 'name' => $northwind->name],
                ],
            ]
        );
        $launchChecklist->assignees()->sync([$user->id]);

        $qaFixes = Note::query()->updateOrCreate(
            [
                'project_id' => $platformLaunch->id,
                'title' => 'Ship QA fixes',
            ],
            [
                'user_id' => $manager->id,
                'team_id' => $productDelivery->id,
                'content' => 'Resolve launch-blocking defects and retest impacted flows.',
                'clipboard_text' => 'Prioritize regressions, confirm retest coverage, publish release notes delta.',
                'status' => Note::STATUS_IN_PROGRESS,
                'progress' => 50,
                'attachments' => [],
                'mentions' => [
                    ['type' => 'User', 'id' => $manager->id, 'name' => $manager->name],
                    ['type' => 'User', 'id' => $user->id, 'name' => $user->name],
                ],
            ]
        );
        $qaFixes->assignees()->sync([$manager->id, $user->id]);

        $retro = Note::query()->updateOrCreate(
            [
                'project_id' => $platformLaunch->id,
                'title' => 'Publish launch retrospective',
            ],
            [
                'user_id' => $hr->id,
                'team_id' => $productDelivery->id,
                'content' => 'Summarize wins, misses, and next sprint follow-up items for the rollout.',
                'clipboard_text' => 'Lessons learned, staffing follow-up, enablement changes.',
                'status' => Note::STATUS_DONE,
                'progress' => 100,
                'attachments' => [],
                'mentions' => [
                    ['type' => 'User', 'id' => $hr->id, 'name' => $hr->name],
                ],
            ]
        );
        $retro->assignees()->sync([$hr->id]);

        $handbook = Note::query()->updateOrCreate(
            [
                'project_id' => $onboardingRefresh->id,
                'title' => 'Rewrite onboarding guide',
            ],
            [
                'user_id' => $hr->id,
                'team_id' => $peopleOperations->id,
                'content' => 'Refresh onboarding content to match the new operating model and support flow.',
                'clipboard_text' => 'Guide v2 outline, manager expectations, first-week checklist.',
                'status' => Note::STATUS_IN_PROGRESS,
                'progress' => 75,
                'attachments' => [],
                'mentions' => [
                    ['type' => 'User', 'id' => $hr->id, 'name' => $hr->name],
                    ['type' => 'Team', 'id' => $peopleOperations->id, 'name' => $peopleOperations->name],
                ],
            ]
        );
        $handbook->assignees()->sync([$hr->id]);

        $welcomeSessions = Note::query()->updateOrCreate(
            [
                'project_id' => $onboardingRefresh->id,
                'title' => 'Schedule welcome sessions',
            ],
            [
                'user_id' => $manager->id,
                'team_id' => $peopleOperations->id,
                'content' => 'Coordinate intro sessions, assign facilitators, and confirm handoff coverage.',
                'clipboard_text' => 'Calendar blocks, facilitators, attendance follow-up.',
                'status' => Note::STATUS_TODO,
                'progress' => 0,
                'attachments' => [],
                'mentions' => [
                    ['type' => 'User', 'id' => $user->id, 'name' => $user->name],
                    ['type' => 'Customer', 'id' => $contoso->id, 'name' => $contoso->name],
                ],
            ]
        );
        $welcomeSessions->assignees()->sync([$hr->id, $user->id]);

        $rolloutApproval = Note::query()->updateOrCreate(
            [
                'project_id' => $onboardingRefresh->id,
                'title' => 'Approve handbook rollout',
            ],
            [
                'user_id' => $manager->id,
                'team_id' => $peopleOperations->id,
                'content' => 'Review and approve the final onboarding handbook rollout plan.',
                'clipboard_text' => 'Approval checklist, sign-off status, launch date.',
                'status' => Note::STATUS_DONE,
                'progress' => 100,
                'attachments' => [],
                'mentions' => [
                    ['type' => 'User', 'id' => $manager->id, 'name' => $manager->name],
                ],
            ]
        );
        $rolloutApproval->assignees()->sync([$manager->id]);
    }
}
