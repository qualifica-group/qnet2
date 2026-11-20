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
 * Spec 0404, part C (AC-016/AC-017, rev. 2026-11-20): a category flagged
 * `requires_contact_transfer_before_contract` refuses the contract's
 * validation until the offer's contact has been transferred.
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

// REQUIREMENT CHANGED (spec 0404 C rev., user decision 2026-11-20): the obligation is
// checked on "Valida contratto" only; the offer moves to Won and its contract is born freely.

if (! function_exists('contactTransferValidator')) {
    function contactTransferValidator(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([Permission::findOrCreate('contracts.validate', 'web'), Permission::findOrCreate('contracts.viewAll', 'web')]);

        return $user;
    }
}

if (! function_exists('contactTransferWonContract')) {
    /** The offer moved to Won through the API, and the contract that it opened. */
    function contactTransferWonContract(Quote $quote): Contract
    {
        QuoteLine::factory()->create(['quote_id' => $quote->id, 'line_type' => QuoteLineType::Revenue]);
        Sanctum::actingAs(contactTransferActor());

        test()->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => contactTransferStatus('closed_won')->id])->assertOk();

        return Contract::where('quote_id', $quote->id)->sole();
    }
}

it('AC-016 rev.: an offer not yet transferred moves to Won and its contract is born', function () {
    $quote = contactTransferQuote(['requires_contact_transfer_before_contract' => true]);

    contactTransferWonContract($quote);

    expect($quote->fresh()->quote_workflow_status_id)->toBe(contactTransferStatus('closed_won')->id);
});

it('AC-016 rev.: validating the contract is refused with 409 until the contact is transferred', function () {
    $quote = contactTransferQuote(['requires_contact_transfer_before_contract' => true]);
    $contract = contactTransferWonContract($quote);
    Sanctum::actingAs(contactTransferValidator());

    $this->postJson("/api/contracts/{$contract->id}/validate")
        ->assertConflict()
        ->assertJsonPath('message', __('quotes.contact_transfer_required_for_contract'));

    expect($contract->fresh()->validated_at)->toBeNull();

    $quote->forceFill(['is_transferred' => true])->saveQuietly();

    $this->postJson("/api/contracts/{$contract->id}/validate")->assertOk();
    expect($contract->fresh()->validated_at)->not->toBeNull();
});

it('AC-016 rev.: a category without the flag validates as before', function () {
    $quote = contactTransferQuote(['requires_contact_transfer_before_contract' => false]);
    $contract = contactTransferWonContract($quote);
    Sanctum::actingAs(contactTransferValidator());

    $this->postJson("/api/contracts/{$contract->id}/validate")->assertOk();
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
