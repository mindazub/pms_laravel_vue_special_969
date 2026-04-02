<?php

namespace Tests\Feature;

use App\Models\Scrapboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScrapboardManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrapboards_page_requires_authentication(): void
    {
        $this->get(route('scrapboards.index'))->assertRedirect(route('login'));
    }

    public function test_first_visit_provisions_starter_scrapboards(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $this
            ->actingAs($user)
            ->get(route('scrapboards.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scrapboards/Index')
                ->has('scrapboards', 2)
                ->where('scrapboards.0.name', 'Scrapboard 1')
                ->where('scrapboards.1.name', 'Scrapboard 2')
                ->where('selectedScrapboard.name', 'Scrapboard 1')
                ->where('selectedScrapboard.workbook.sheets.0.name', 'Sheet 1')
                ->where('selectedScrapboard.workbook.sheets.0.rows', 200)
                ->where('selectedScrapboard.workbook.sheets.0.columns', 26)
                ->where('selectedScrapboard.workbook.sheets.0.columnWidths.0', 64)
                ->where('selectedScrapboard.workbook.sheets.1.name', 'Sheet 2')
            );

        $this->assertDatabaseCount('scrapboards', 2);
    }

    public function test_authenticated_user_can_create_update_and_delete_their_scrapboard(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $createResponse = $this
            ->actingAs($user)
            ->postJson(route('scrapboards.store'), [
                'name' => 'Launch Planning Grid',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('name', 'Launch Planning Grid')
            ->assertJsonPath('workbook.sheets.0.name', 'Sheet 1')
            ->assertJsonPath('workbook.sheets.0.rows', 200)
            ->assertJsonPath('workbook.sheets.0.columns', 26)
            ->assertJsonPath('workbook.sheets.0.columnWidths.0', 64);

        $scrapboardId = $createResponse->json('id');

        $this
            ->actingAs($user)
            ->putJson(route('scrapboards.update', $scrapboardId), [
                'name' => 'Launch Planning Grid v2',
                'workbook' => [
                    'activeSheetId' => 'sheet-1',
                    'sheets' => [
                        [
                            'id' => 'sheet-1',
                            'name' => 'Sheet 1',
                            'rows' => 12,
                            'columns' => 6,
                            'columnWidths' => [120, 64, 64, 64, 64, 64],
                            'cells' => [
                                '0:0' => [
                                    'value' => 'Owner',
                                    'background' => '#fef3c7',
                                    'fontWeight' => '700',
                                ],
                                '1:1' => [
                                    'value' => 'Budget',
                                    'background' => '#bfdbfe',
                                ],
                            ],
                        ],
                        [
                            'id' => 'sheet-2',
                            'name' => 'Sheet 2',
                            'rows' => 12,
                            'columns' => 6,
                            'cells' => [],
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Launch Planning Grid v2')
            ->assertJsonPath('workbook.sheets.0.columnWidths.0', 120)
            ->assertJsonPath('workbook.sheets.0.cells.0:0.value', 'Owner')
            ->assertJsonPath('workbook.sheets.0.cells.1:1.value', 'Budget');

        $this->assertDatabaseHas('scrapboards', [
            'id' => $scrapboardId,
            'user_id' => $user->id,
            'name' => 'Launch Planning Grid v2',
        ]);

        $this
            ->actingAs($user)
            ->deleteJson(route('scrapboards.destroy', $scrapboardId))
            ->assertOk();

        $this->assertDatabaseMissing('scrapboards', [
            'id' => $scrapboardId,
        ]);
    }

    public function test_user_cannot_view_or_delete_another_users_scrapboard(): void
    {
        /** @var User $owner */
        $owner = User::factory()->createOne();
        /** @var User $intruder */
        $intruder = User::factory()->createOne();

        $scrapboard = Scrapboard::factory()->createOne([
            'user_id' => $owner->id,
            'name' => 'Private Board',
        ]);

        $this
            ->actingAs($intruder)
            ->get(route('scrapboards.show', $scrapboard))
            ->assertForbidden();

        $this
            ->actingAs($intruder)
            ->deleteJson(route('scrapboards.destroy', $scrapboard))
            ->assertForbidden();
    }
}
