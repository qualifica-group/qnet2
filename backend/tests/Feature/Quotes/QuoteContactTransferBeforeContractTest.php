<?php

declare(strict_types=1);

use App\Enums\QuoteLineType;
use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0404, part C (AC-016/AC-017): a category flagged
 * `requires_contact_transfer_before_contract` refuses the positive close that
 * would open a contract until the offer's contact has been transferred.
 */
uses(RefreshDatabase::class);

if (! function_exists('contactTransferActor')) {
    function contactTransferActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.viewAny', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.viewAll']);

        return $user;
    }
}

if (! function_exists('contactTransferQuote')) {
    /**
     * An open offer whose opportunity covers one category with the given flags.
     *
     * @param  array<string, bool>  $categoryFlags
     */
    function contactTransferQuote(array $categoryFlags, bool $transferred = false): Quote
    {
        $category = ProductCategory::factory()->create($categoryFlags);
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create(['opportunity_id' => $opportunity->id, 'product_category_id' => $category->id]);
        Product::factory()->create(['category_id' => $category->id]);

        $quote = Quote::factory()->create([
            'opportunity_id' => $opportunity->id,
            'quote_workflow_status_id' => QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open])->id,
        ]);
        $quote->forceFill(['is_transferred' => $transferred])->saveQuietly();

        return $quote;
    }
}

if (! function_exists('contactTransferStatus')) {
    function contactTransferStatus(string $systemKey): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', $systemKey)->sole();
    }
}

it('AC-016: refuses the positive close of an offer not yet transferred', function () {
    $quote = contactTransferQuote(['requires_contact_transfer_before_contract' => true]);
    $before = $quote->quote_workflow_status_id;
    Sanctum::actingAs(contactTransferActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => contactTransferStatus('closed_won')->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quote_workflow_status_id' => __('quotes.contact_transfer_required_for_contract')]);

    expect($quote->fresh()->quote_workflow_status_id)->toBe($before)
        ->and(Contract::where('quote_id', $quote->id)->exists())->toBeFalse();
});

it('AC-016: lets a transferred offer close positively and opens the contract', function () {
    $quote = contactTransferQuote(['requires_contact_transfer_before_contract' => true], transferred: true);
    Sanctum::actingAs(contactTransferActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => contactTransferStatus('closed_won')->id])->assertOk();

    expect(Contract::where('quote_id', $quote->id)->exists())->toBeTrue();
});

it('AC-016: does not block a branch that generates no contract', function () {
    $quote = contactTransferQuote(['requires_contact_transfer_before_contract' => true, 'generates_contract' => false]);
    $closedWon = contactTransferStatus('closed_won');
    Sanctum::actingAs(contactTransferActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $closedWon->id])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id);
});

it('AC-016: a category without the flag closes as before', function () {
    $quote = contactTransferQuote(['requires_contact_transfer_before_contract' => false]);
    Sanctum::actingAs(contactTransferActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => contactTransferStatus('closed_won')->id])->assertOk();

    expect(Contract::where('quote_id', $quote->id)->exists())->toBeTrue();
});

it('AC-017: the provisional win stays free without the transfer', function () {
    $quote = contactTransferQuote(['requires_contact_transfer_before_contract' => true]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'line_type' => QuoteLineType::Revenue]);
    $validated = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Validated]);
    Sanctum::actingAs(contactTransferActor());

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $validated->id])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($validated->id);
});

it('AC-017: the flag is saved and read back on the product category', function () {
    Permission::findOrCreate('product-categories.update');
    Permission::findOrCreate('product-categories.view');
    $user = User::factory()->create();
    $user->givePermissionTo(['product-categories.update', 'product-categories.view']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson("/api/product-categories/{$category->id}", ['requires_contact_transfer_before_contract' => true])
        ->assertOk()
        ->assertJsonPath('data.requires_contact_transfer_before_contract', true);

    $this->getJson("/api/product-categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.requires_contact_transfer_before_contract', true);
});
