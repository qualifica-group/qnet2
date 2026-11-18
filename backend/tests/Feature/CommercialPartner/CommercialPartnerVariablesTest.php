<?php

declare(strict_types=1);

use App\Enums\DocumentLayoutModule;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;
use App\Services\OutboundEmails\WorkOrderEmailVariableCatalog;
use App\Services\OutboundEmails\WorkOrderEmailVariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0417 AC-015 / D-8: the Partner commerciale in document-layout variables,
// work-order email variables, the contract and the work order.

uses(RefreshDatabase::class);

function partnerReferent(string $name): Referent
{
    $referent = Referent::factory()->create(['name' => $name]);
    $card = PersonalData::factory()->for($referent, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'partner@example.com', 'is_primary' => true]);
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '+39 02 999999', 'is_primary' => true]);

    return $referent;
}

it('the document-layout catalog carries the commercial_partner category with name/email/phone, after commercial', function () {
    $categories = collect(app(DocumentLayoutVariableCatalog::class)->categoriesFor(DocumentLayoutModule::Quotes, User::factory()->create()));
    $keys = $categories->pluck('key')->all();
    $partner = $categories->firstWhere('key', 'commercial_partner');

    expect(array_search('commercial_partner', $keys, true))->toBe(array_search('commercial', $keys, true) + 1)
        ->and(collect($partner['variables'])->pluck('variable')->all())
        ->toBe(['{commercial_partner.name}', '{commercial_partner.email}', '{commercial_partner.phone}']);
});

it('the work-order email catalog delegates the commercial_partner category', function () {
    $keys = collect(app(WorkOrderEmailVariableCatalog::class)->categoriesFor(User::factory()->create()))->pluck('key')->all();

    expect($keys)->toContain('commercial_partner');
});

it('work-order email variables resolve {commercial_partner.*} from the quote partner, empty when absent', function () {
    $partner = partnerReferent('Paola Partner');
    $quote = Quote::factory()->create(['commercial_partner_id' => $partner->id]);
    $workOrder = WorkOrder::factory()->for($quote)->create();
    $actor = User::factory()->create();

    $result = app(WorkOrderEmailVariableResolver::class)->render(
        'P: {commercial_partner.name}',
        '{commercial_partner.name}|{commercial_partner.email}|{commercial_partner.phone}',
        $workOrder,
        $actor,
    );

    expect($result['subject'])->toBe('P: Paola Partner')
        ->and($result['body'])->toBe('Paola Partner|partner@example.com|+39 02 999999');

    $bare = WorkOrder::factory()->for(Quote::factory()->create(['commercial_partner_id' => null]))->create();

    expect(app(WorkOrderEmailVariableResolver::class)->render('x', '[{commercial_partner.name}]', $bare, $actor)['body'])->toBe('[]');
});

it('the contract detail exposes the quote partner', function () {
    $partner = Referent::factory()->create(['name' => 'Paola Partner']);
    $contract = Contract::factory()->create(['quote_id' => Quote::factory()->create(['commercial_partner_id' => $partner->id])->id]);
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('contracts.view', 'web'), Permission::findOrCreate('contracts.viewAll', 'web'));
    Sanctum::actingAs($actor);

    $this->getJson("/api/contracts/{$contract->id}")->assertOk()
        ->assertJsonPath('data.commercial_partner.id', $partner->id)
        ->assertJsonPath('data.commercial_partner.name', 'Paola Partner');
});

it('the work order exposes the quote partner and the registry partner in client_info', function () {
    $quotePartner = Referent::factory()->create(['name' => 'Quote Partner']);
    $registryPartner = Referent::factory()->create(['name' => 'Registry Partner']);
    $registry = Registry::factory()->create(['commercial_partner_id' => $registryPartner->id]);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $workOrder = WorkOrder::factory()->create([
        'quote_id' => Quote::factory()->create(['opportunity_id' => $opportunity->id, 'commercial_partner_id' => $quotePartner->id])->id,
    ]);
    $actor = workOrderUserWith(['view']);
    Permission::findOrCreate('registries.view');
    Permission::findOrCreate('referents.view');
    $actor->givePermissionTo('registries.view', 'referents.view');
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()
        ->assertJsonPath('data.commercial_partner.name', 'Quote Partner')
        ->assertJsonPath('data.client_info.commercial_partner.name', 'Registry Partner');
});
