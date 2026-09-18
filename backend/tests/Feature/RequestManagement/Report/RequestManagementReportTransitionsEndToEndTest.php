<?php

declare(strict_types=1);

use App\Enums\RequestManagementReportRowMode;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// "N. Potenziali associati" / "Associati" checked END TO END (user directive
// 2026-09-18, "controllare se funziona correttamente"): the status change goes
// through the real Gestione Richieste PATCH, and the report must see the
// activity entry that write produces — no hand-written activity_log row.

uses(RefreshDatabase::class);

function transitionsEndToEndActor(): User
{
    foreach (['viewAny', 'view', 'update', 'viewAll', 'report'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.view', 'request-management.update', 'request-management.viewAll', 'request-management.report']);

    return $actor;
}

function transitionsEndToEndQuote(ProductCategory $category, User $operator): Quote
{
    // A fiscal identity: a positive close demands one (RequestWorkflowStatusWriter).
    $registry = Registry::factory()->create();
    $registry->personalData()->create([
        'type' => 'individual',
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'tax_code' => 'RSSMRA80A01H501U',
    ]);

    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $category->id,
    ]);

    return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
}

/**
 * @return array<string, int>
 */
function transitionsEndToEndTotals(User $actor, ProductCategory $category): array
{
    $today = now()->toDateString();
    [$branch] = app(RequestManagementReportGenerator::class)->rows(
        $actor,
        $today,
        $today,
        [(string) $category->id],
        RequestManagementReportRowMode::TotalOnly,
    );

    return $branch['rows'][0]->values;
}

it('counts a request moved to a pending status from Gestione Richieste as a potential', function () {
    $gol = ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
    $actor = transitionsEndToEndActor();
    $quote = transitionsEndToEndQuote($gol, $actor);
    $pending = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Pending, 'requires_note' => false]);

    expect(transitionsEndToEndTotals($actor, $gol)['potenziali'])->toBe(0);

    Sanctum::actingAs($actor);
    $this->patchJson("/api/request-management/{$quote->id}", ['quote_workflow_status_id' => $pending->id])->assertOk();

    expect(transitionsEndToEndTotals($actor, $gol)['potenziali'])->toBe(1);
});

it('counts a request moved to a validated status from Gestione Richieste as a potential', function () {
    $gol = ProductCategory::factory()->reportable()->create([
        'name' => 'GOL',
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $actor = transitionsEndToEndActor();
    $quote = transitionsEndToEndQuote($gol, $actor);
    $validated = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Validated, 'requires_note' => false]);

    // A validating destination demands an offer line (spec 0102): sent in the same request.
    $product = Product::factory()->create(['category_id' => $gol->id]);

    Sanctum::actingAs($actor);
    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $validated->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertOk();

    expect(transitionsEndToEndTotals($actor, $gol)['potenziali'])->toBe(1);
});

it('counts a request moved to a pending status from the Offerte module as a potential', function () {
    $gol = ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }
    $actor = transitionsEndToEndActor();
    $actor->givePermissionTo(['quotes.viewAny', 'quotes.view', 'quotes.update']);
    $quote = transitionsEndToEndQuote($gol, $actor);
    $pending = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Pending, 'requires_note' => false]);

    Sanctum::actingAs($actor);
    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $pending->id])->assertOk();

    expect(transitionsEndToEndTotals($actor, $gol)['potenziali'])->toBe(1);
});
