<?php

declare(strict_types=1);

use App\Enums\RequestManagementReportRowMode;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

// Spec 0169 AC-002/003/004: either bound of the period may be open, and an
// open side adds no constraint — checked on "N. Telefonate Effettuate", one
// note before, one inside and one after September 2026.

uses(RefreshDatabase::class);

function openRangePhoneCalls(User $actor, ProductCategory $category, ?string $dateFrom, ?string $dateTo): ?int
{
    [$branch] = app(RequestManagementReportGenerator::class)->rows(
        $actor,
        $dateFrom,
        $dateTo,
        [(string) $category->id],
        RequestManagementReportRowMode::TotalOnly,
    );

    return $branch['rows'][0]->values['telefonate'];
}

it('counts only what each open or closed bound lets through', function (?string $dateFrom, ?string $dateTo, int $expected) {
    $category = ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
    $operator = User::factory()->create();

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $category->id,
    ]);
    // Past the first state: a request still "open" has no phone calls (directive 2026-09-18).
    $status = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Open]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $status->id]);
    $quote->forceFill(['operator_id' => $operator->id])->save();

    foreach (['2026-08-01', '2026-09-10', '2026-10-20 23:30:00'] as $createdAt) {
        Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $opportunity->id,
            'user_id' => $operator->id,
            'created_at' => Carbon::parse($createdAt),
        ])->forceFill(['quote_id' => $quote->id])->save();
    }

    Permission::findOrCreate('request-management.viewAll');
    $actor = User::factory()->create();
    $actor->givePermissionTo('request-management.viewAll');

    expect(openRangePhoneCalls($actor, $category, $dateFrom, $dateTo))->toBe($expected);
})->with([
    'both bounds' => ['2026-09-01', '2026-09-30', 1],
    'only date_to' => [null, '2026-09-30', 2],
    'only date_from' => ['2026-09-01', null, 2],
    'only date_from, last day included' => ['2026-10-20', null, 1],
    'neither' => [null, null, 3],
]);
