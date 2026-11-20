<?php

declare(strict_types=1);

use App\Authorization\QuotesAuthorization;
use App\Authorization\RequestManagementAuthorization;
use App\Models\Contract;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\Contracts\ContractStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * User decisions 2026-11-20: an offer moves to another anagrafica or Sede
 * only while its contract, if any, has not been worked on; the contact
 * transfer also stays open on an unvalidated contract that still owes it.
 */
uses(RefreshDatabase::class);

if (! function_exists('transferAvailabilityActor')) {
    function transferAvailabilityActor(): User
    {
        $abilities = [
            'quotes.update', 'quotes.viewAll', 'quotes.transferRegistry',
            'request-management.update', 'request-management.viewAll',
            'request-management.transferContact', 'request-management.transferRegistry',
        ];
        $user = User::factory()->create();
        $user->givePermissionTo(array_map(static fn (string $name): Permission => Permission::findOrCreate($name, 'web'), $abilities));

        return $user;
    }
}

if (! function_exists('transferAvailabilityQuote')) {
    /**
     * @param  array<string, mixed>|null  $contract  null = no contract; the attributes of its contract otherwise
     */
    function transferAvailabilityQuote(?array $contract, bool $owesTransfer = false): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $category = ProductCategory::factory()->create(['requires_contact_transfer_before_contract' => $owesTransfer]);
        OpportunityProductLine::factory()->create(['opportunity_id' => $opportunity->id, 'product_category_id' => $category->id]);
        $quote = Quote::factory()->for($opportunity)->create();

        if ($contract !== null) {
            Contract::factory()->create([
                'quote_id' => $quote->id,
                'contract_status_id' => app(ContractStatusResolver::class)->defaultActiveId(),
                ...$contract,
            ]);
        }

        return $quote;
    }
}

if (! function_exists('transferAvailabilityFlags')) {
    /**
     * @return array{contact: bool, request_registry: bool, quote_registry: bool}
     */
    function transferAvailabilityFlags(Quote $quote): array
    {
        $actor = transferAvailabilityActor();
        $requests = app(RequestManagementAuthorization::class)->actionPermissions($actor, $quote);

        return [
            'contact' => $requests['transfer_contact'],
            'request_registry' => $requests['transfer_registry'],
            'quote_registry' => app(QuotesAuthorization::class)->actionPermissions($actor, $quote)['transfer_registry'],
        ];
    }
}

it('offers both transfers on an offer with no contract or with a contract nobody worked on', function (?array $contract) {
    expect(transferAvailabilityFlags(transferAvailabilityQuote($contract)))
        ->toBe(['contact' => true, 'request_registry' => true, 'quote_registry' => true]);
})->with([
    'no contract' => [null],
    'untouched contract' => [[]],
]);

it('closes both transfers once the contract was worked on', function () {
    $quote = transferAvailabilityQuote(['payment_notes' => 'Bonifico']);

    expect(transferAvailabilityFlags($quote))
        ->toBe(['contact' => false, 'request_registry' => false, 'quote_registry' => false]);
});

it('keeps the contact transfer on a worked-on contract that still owes it, until it is validated', function () {
    $owing = transferAvailabilityQuote(['payment_notes' => 'Bonifico'], owesTransfer: true);
    $validated = transferAvailabilityQuote(['validated_at' => now()->toDateString()], owesTransfer: true);

    expect(transferAvailabilityFlags($owing))
        ->toBe(['contact' => true, 'request_registry' => false, 'quote_registry' => false])
        ->and(transferAvailabilityFlags($validated)['contact'])->toBeFalse();
});

it('refuses the contact transfer of a worked-on contract with 409, without writes', function () {
    $quote = transferAvailabilityQuote(['payment_notes' => 'Bonifico']);
    $originSiteId = $quote->operational_site_id;
    Sanctum::actingAs(transferAvailabilityActor());

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => OperationalSite::factory()->withAddress()->create()->id,
        'operator_id' => User::factory()->create()->id,
    ])
        ->assertConflict()
        ->assertJsonPath('message', __('request-management.transfer_blocked_by_contract', ['codes' => $quote->code]));

    expect($quote->fresh()->operational_site_id)->toBe($originSiteId)
        ->and($quote->fresh()->is_transferred)->toBeFalse();
});

it('transfers the contact of an offer whose contract nobody worked on, and the contract follows', function () {
    $quote = transferAvailabilityQuote([]);
    $destination = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs(transferAvailabilityActor());

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destination->id,
        'operator_id' => User::factory()->create()->id,
    ])->assertOk()->assertJsonPath('data.transferred', 1);

    expect(Contract::where('quote_id', $quote->id)->sole()->quote->operational_site_id)->toBe($destination->id);
});
