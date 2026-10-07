<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0206: the quotes grid edits its cells through the form's own
// UpdateQuoteRequest + QuoteService (QuoteCellWriter).

uses(RefreshDatabase::class);

function quoteGridEditor(): User
{
    // The transition note lands on the request-management thread (as from the form).
    $notePermissions = ['notes.create', 'request-management.view', 'request-management.viewAll'];
    foreach ($notePermissions as $permission) {
        Permission::findOrCreate($permission);
    }
    $actor = quoteTableUserWith(['viewAny', 'view', 'update']);
    $actor->givePermissionTo($notePermissions);

    return $actor;
}

function quoteGridQuote(array $attributes = []): Quote
{
    return Quote::factory()->create(['opportunity_id' => Opportunity::factory(), ...$attributes]);
}

function patchQuoteCell(Quote $quote, string $column, mixed $value, ?string $note = null): TestResponse
{
    $payload = ['column' => $column, 'value' => $value];

    return test()->patchJson("/api/tables/quotes/rows/{$quote->id}", $note === null ? $payload : [...$payload, 'note' => $note]);
}

it('declares every column the form edits as a single field', function () {
    Sanctum::actingAs(quoteGridEditor());

    $editable = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'))
        ->where('editable', true)->pluck('id')->sort()->values()->all();

    expect($editable)->toBe([
        'commercial', 'company', 'company_site', 'managers', 'operational_site',
        'quote_workflow_status', 'reporter', 'supervisor', 'title',
    ]);
});

it('saves the title and the team through the form path', function () {
    Sanctum::actingAs(quoteGridEditor());
    $quote = quoteGridQuote();
    $commercial = Referent::factory()->create();
    $supervisor = User::factory()->create();

    patchQuoteCell($quote, 'title', 'Offerta rinominata')->assertOk()->assertJsonPath('data.title', 'Offerta rinominata');
    patchQuoteCell($quote, 'commercial', $commercial->id)->assertOk()->assertJsonPath('data.commercial.id', $commercial->id);
    patchQuoteCell($quote, 'supervisor', $supervisor->id)->assertOk()->assertJsonPath('data.supervisor.id', $supervisor->id);
});

it('clears the company site when the company changes, as the form does', function () {
    Sanctum::actingAs(quoteGridEditor());
    $company = Company::factory()->create();
    $site = CompanySite::factory()->create(['company_id' => $company->id]);
    $quote = quoteGridQuote(['company_id' => $company->id, 'company_site_id' => $site->id]);
    $other = Company::factory()->create();

    patchQuoteCell($quote, 'company', $other->id)->assertOk()->assertJsonPath('data.company_site', null);

    expect($quote->fresh()->company_id)->toBe($other->id);
});

it('applies the form cross-check: a site of another company is refused', function () {
    Sanctum::actingAs(quoteGridEditor());
    $company = Company::factory()->create();
    $quote = quoteGridQuote(['company_id' => $company->id]);
    $foreignSite = CompanySite::factory()->create(['company_id' => Company::factory()]);

    patchQuoteCell($quote, 'company_site', $foreignSite->id)->assertUnprocessable();

    expect($quote->fresh()->company_site_id)->toBeNull();
});

it('requires the transition note when the target status asks for it', function () {
    Sanctum::actingAs(quoteGridEditor());
    $quote = quoteGridQuote();
    $target = QuoteWorkflowStatus::factory()->global()->create(['requires_note' => true, 'sort_order' => 99]);

    patchQuoteCell($quote, 'quote_workflow_status', $target->id)->assertUnprocessable();
    patchQuoteCell($quote, 'quote_workflow_status', $target->id, 'Cliente in attesa di budget')->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($target->id);
});

it('offers the per-row status set and the domain catalogue', function () {
    Sanctum::actingAs(quoteGridEditor());
    $quote = quoteGridQuote();

    $columns = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'));
    $row = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))
        ->firstWhere('id', $quote->id);

    expect($columns->firstWhere('id', 'quote_workflow_status')['options'])->not->toBeEmpty()
        ->and($row['quote_workflow_status_options'])->toContain($quote->quote_workflow_status_id);
});

it('keeps code and aggregates read-only', function () {
    Sanctum::actingAs(quoteGridEditor());
    $quote = quoteGridQuote();

    patchQuoteCell($quote, 'code', 'X-1')->assertUnprocessable();
    patchQuoteCell($quote, 'revenue_net', 10)->assertUnprocessable();
});

it('forbids the write without quotes.update', function () {
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view']));
    $quote = quoteGridQuote(['title' => 'Originale']);

    patchQuoteCell($quote, 'title', 'Tentativo')->assertForbidden();
});
