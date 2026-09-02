<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Standalone campaign, "Nuovo" status fallback (spec 0039, D-3, AC-009)
|--------------------------------------------------------------------------
|
| Split out of CampaignCrudTest.php (file-size split, engineering.md §6):
| a standalone campaign's pipeline_status_id went from `required` to
| `nullable` — an omitted FK falls back to the mandatory system_key='new'
| status. Linked campaigns are UNCHANGED (still `prohibited`, BR-2).
*/

if (! function_exists('campaignFallbackUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function campaignFallbackUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

/**
 * A standalone campaign's `product_lines` (spec 0094) + country_id (BR-4),
 * DELIBERATELY without pipeline_status_id.
 *
 * @return array<string, mixed>
 */
if (! function_exists('standaloneFieldsWithoutStatus')) {
    function standaloneFieldsWithoutStatus(): array
    {
        // Coherent pair (spec 0023 REV): the category sits under the business function.
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'product_lines' => [[
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]],
            'country_id' => Country::factory()->create()->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];
    }
}

it('create: standalone missing pipeline_status_id -> 201, falls back to the system_key=new status (AC-009)', function () {
    $actor = campaignFallbackUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'No Status Standalone'], standaloneFieldsWithoutStatus()))
        ->assertCreated();

    $campaign = Campaign::query()->with('pipelineStatus')->where('name', 'No Status Standalone')->sole();
    expect($campaign->pipelineStatus->system_key)->toBe('new');
});
