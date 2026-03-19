<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Workload2025Test extends TestCase
{
    use RefreshDatabase;

    public function test_workload_2025_pages_require_authentication(): void
    {
        $this->get(route('projects.2025'))->assertRedirect(route('login'));
        $this->get(route('dashboard.2025'))->assertRedirect(route('login'));
    }

    public function test_dry_run_import_validates_without_persisting_rows(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $response = $this
            ->actingAs($user)
            ->post(route('projects.2025.import'), [
                'file' => $this->sampleCsvUpload(),
                'dry_run' => true,
            ]);

        $response
            ->assertRedirect(route('projects.2025'))
            ->assertSessionHas('workload2025ImportSummary');

        $this->assertSame(0, Note::query()->workload2025()->count());
    }

    public function test_import_creates_dataset_and_projects_2025_filters_by_owner(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $this
            ->actingAs($user)
            ->post(route('projects.2025.import'), [
                'file' => $this->sampleCsvUpload(),
            ])
            ->assertRedirect(route('projects.2025'));

        $this->assertSame(3, Note::query()->workload2025()->count());

        $response = $this
            ->actingAs($user)
            ->get(route('projects.2025', ['owner' => 'Jonas']));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects2025/Index')
                ->where('summary.total_tasks', 1)
                ->has('rows.data', 1)
                ->where('rows.data.0.owner', 'Jonas')
                ->where('rows.data.0.task', 'Prepare proposal')
            );
    }

    public function test_dashboard_2025_returns_kpis_for_filtered_week(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $this
            ->actingAs($user)
            ->post(route('projects.2025.import'), [
                'file' => $this->sampleCsvUpload(),
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard.2025', ['week' => 'W48']));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard2025')
                ->where('kpis.total_tasks', 2)
                ->where('kpis.blocked_tasks', 1)
                ->has('weeklyTrend', 1)
                ->where('weeklyTrend.0.week_label', 'W48')
            );
    }

    private function sampleCsvUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'workload-2025.csv',
            file_get_contents(base_path('tests/Fixtures/workload_2025_sample.csv'))
        );
    }
}
