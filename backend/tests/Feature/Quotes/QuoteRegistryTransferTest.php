<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use App\Services\Quotes\QuoteRegistryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0404, part B (AC-010..AC-014): moving an offer to another anagrafica.
 */
uses(RefreshDatabase::class);

if (! function_exists('registryTransferActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryTransferActor(array $abilities = ['update', 'transferRegistry'], string $resource = 'quotes'): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'viewActivity', 'transferRegistry'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(static fn (string $ability): string => "{$resource}.{$ability}", [...$abilities, 'view', 'viewAll', 'viewActivity']));

        return $user;
    }
}

if (! function_exists('registryTransferQuote')) {
    /**
     * An open offer on an opportunity covering one category.
     *
     * @return array{quote: Quote, opportunity: Opportunity, category: ProductCategory}
     */
    function registryTransferQuote(bool $multipleOpen = false, bool $singleQuote = false): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'multiple_open_opportunities' => $multipleOpen,
            'single_quote_per_opportunity' => $singleQuote,
        ]);
        $opportunity = Opportunity::factory()->create(['commercial_id' => null, 'reporter_id' => null]);
        OpportunityProductLine::factory()->create(['opportunity_id' => $opportunity->id, 'product_category_id' => $category->id]);

        $quote = Quote::factory()->create([
            'opportunity_id' => $opportunity->id,
            'quote_workflow_status_id' => QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open])->id,
        ]);

        return ['quote' => $quote, 'opportunity' => $opportunity, 'category' => $category];
    }
}

it('AC-011: creates a new opportunity on the destination with the old lines and site, no referent', function () {
    ['quote' => $quote, 'opportunity' => $origin, 'category' => $category] = registryTransferQuote();
    $destination = Registry::factory()->create();
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => $destination->id])
        ->assertOk()
        ->assertJsonPath('data.registry_transfer.to_registry_name', $destination->name);

    $target = $quote->fresh()->opportunity;

    expect($target->id)->not->toBe($origin->id)
        ->and($target->registry_id)->toBe($destination->id)
        ->and($target->referent_id)->toBeNull()
        ->and($target->operational_site_id)->toBe($origin->operational_site_id)
        ->and($target->productLines()->pluck('product_category_id')->all())->toBe([$category->id])
        ->and(Opportunity::find($origin->id))->not->toBeNull();
});

it('AC-010: lands on the open opportunity of the destination when a second one is not allowed', function () {
    ['quote' => $quote, 'category' => $category] = registryTransferQuote();
    $destination = Registry::factory()->create();
    $open = Opportunity::factory()->create(['registry_id' => $destination->id]);
    OpportunityProductLine::factory()->create(['opportunity_id' => $open->id, 'product_category_id' => $category->id]);
    $before = Opportunity::count();
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => $destination->id])->assertOk();

    expect($quote->fresh()->opportunity_id)->toBe($open->id)
        ->and(Opportunity::count())->toBe($before);
});

it('AC-011: a branch allowing several open opportunities gets a new one beside the open one', function () {
    ['quote' => $quote, 'category' => $category] = registryTransferQuote(multipleOpen: true);
    $destination = Registry::factory()->create();
    $open = Opportunity::factory()->create(['registry_id' => $destination->id]);
    OpportunityProductLine::factory()->create(['opportunity_id' => $open->id, 'product_category_id' => $category->id]);
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => $destination->id])->assertOk();

    expect($quote->fresh()->opportunity_id)->not->toBe($open->id)
        ->and($quote->fresh()->opportunity->registry_id)->toBe($destination->id);
});

it('AC-012: the scoped notes travel, the opportunity ones stay, and the three records log the move', function () {
    ['quote' => $quote, 'opportunity' => $origin] = registryTransferQuote();
    $fromName = $origin->registry->name;
    $destination = Registry::factory()->create();
    $scoped = Note::factory()->create(['notable_type' => $origin->getMorphClass(), 'notable_id' => $origin->id]);
    $scoped->forceFill(['quote_id' => $quote->id])->save();
    $general = Note::factory()->create(['notable_type' => $origin->getMorphClass(), 'notable_id' => $origin->id]);
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => $destination->id])->assertOk();

    $target = $quote->fresh()->opportunity;
    $entries = Activity::query()->where('event', QuoteRegistryTransferService::EVENT)->get();

    expect($scoped->fresh()->notable_id)->toBe($target->id)
        ->and($general->fresh()->notable_id)->toBe($origin->id)
        ->and($entries)->toHaveCount(3)
        ->and($entries->map(fn (Activity $entry): string => $entry->subject_type.':'.$entry->subject_id)->sort()->values()->all())
        ->toBe(collect(['opportunity:'.$origin->id, 'opportunity:'.$target->id, 'quote:'.$quote->id])->sort()->values()->all())
        ->and($entries->first()->properties['from']['name'])->toBe($fromName)
        ->and($entries->first()->properties['to']['name'])->toBe($destination->name);

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.registry_transfer.from_registry_name', $fromName)
        ->assertJsonPath('data.registry_transfer.to_registry_name', $destination->name);

    $change = collect($this->getJson("/api/activity-log/quotes/{$quote->id}")->assertOk()->json('data.items'))
        ->firstWhere('event', QuoteRegistryTransferService::EVENT)['changes'][0];

    expect($change)->toMatchArray(['field' => 'registry_id', 'old_display' => $fromName, 'new_display' => $destination->name]);
});

it('AC-014: 422 on the same anagrafica, without writes', function () {
    ['quote' => $quote, 'opportunity' => $origin] = registryTransferQuote();
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => $origin->registry_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['registry_id' => __('quotes.registry_transfer.same_registry')]);

    expect($quote->fresh()->opportunity_id)->toBe($origin->id);
});

it('AC-014: 422 on an offer under contract, without writes', function () {
    ['quote' => $quote, 'opportunity' => $origin] = registryTransferQuote();
    Contract::factory()->create(['quote_id' => $quote->id]);
    $before = Opportunity::count();
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => Registry::factory()->create()->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['registry_id' => __('quotes.registry_transfer.contracted')]);

    expect($quote->fresh()->opportunity_id)->toBe($origin->id)
        ->and(Opportunity::count())->toBe($before);
});

it('AC-014: 422 when the destination opportunity already holds its single offer', function () {
    ['quote' => $quote, 'opportunity' => $origin, 'category' => $category] = registryTransferQuote(singleQuote: true);
    $destination = Registry::factory()->create();
    $open = Opportunity::factory()->create(['registry_id' => $destination->id]);
    OpportunityProductLine::factory()->create(['opportunity_id' => $open->id, 'product_category_id' => $category->id]);
    Quote::factory()->create(['opportunity_id' => $open->id]);
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => $destination->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('registry_id');

    expect($quote->fresh()->opportunity_id)->toBe($origin->id);
});

it('AC-014: 403 without quotes.transferRegistry', function () {
    ['quote' => $quote, 'opportunity' => $origin] = registryTransferQuote();
    Sanctum::actingAs(registryTransferActor(['update']));

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => Registry::factory()->create()->id])
        ->assertForbidden();

    expect($quote->fresh()->opportunity_id)->toBe($origin->id);
});

it('AC-014: the Gestione Richieste route authorizes with its own permission set', function () {
    ['quote' => $quote] = registryTransferQuote();
    $destination = Registry::factory()->create();

    Sanctum::actingAs(registryTransferActor(['update'], 'request-management'));
    $this->postJson("/api/request-management/{$quote->id}/transfer-registry", ['registry_id' => $destination->id])
        ->assertForbidden();

    Sanctum::actingAs(registryTransferActor(['update', 'transferRegistry'], 'request-management'));
    $this->postJson("/api/request-management/{$quote->id}/transfer-registry", ['registry_id' => $destination->id])
        ->assertOk()
        ->assertJsonPath('data.registry_id', $destination->id);
});

it('AC-013: an invalid "Informazioni aggiuntive" value rolls the whole move back', function () {
    ['quote' => $quote, 'opportunity' => $origin] = registryTransferQuote();
    $before = Opportunity::count();
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", [
        'registry_id' => Registry::factory()->create()->id,
        'attribute_values' => ['not_an_attribute' => 'x'],
    ])->assertUnprocessable();

    expect($quote->fresh()->opportunity_id)->toBe($origin->id)
        ->and(Opportunity::count())->toBe($before);
});

it('AC-015b: a title typed in the dialog is saved as manual', function () {
    ['quote' => $quote] = registryTransferQuote();
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", [
        'registry_id' => Registry::factory()->create()->id,
        'title' => 'Offerta per il nuovo cliente',
    ])->assertOk()->assertJsonPath('data.title', 'Offerta per il nuovo cliente');

    expect($quote->fresh()->title)->toBe('Offerta per il nuovo cliente')
        ->and($quote->fresh()->title_is_manual)->toBeTrue();
});

it('AC-015b: a blank title goes back to the automatic one, an absent title is left as it is', function () {
    ['quote' => $quote] = registryTransferQuote();
    $quote->forceFill(['title' => 'Titolo a mano', 'title_is_manual' => true])->save();
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => Registry::factory()->create()->id])
        ->assertOk();

    expect($quote->fresh()->title)->toBe('Titolo a mano')
        ->and($quote->fresh()->title_is_manual)->toBeTrue();

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", [
        'registry_id' => Registry::factory()->create()->id,
        'title' => '',
    ])->assertOk();

    expect($quote->fresh()->title)->not->toBe('Titolo a mano')
        ->and($quote->fresh()->title_is_manual)->toBeFalse();
});

it('AC-015b: a title over 191 characters is refused without moving the offer', function () {
    ['quote' => $quote, 'opportunity' => $origin] = registryTransferQuote();
    $title = $quote->title;
    Sanctum::actingAs(registryTransferActor());

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", [
        'registry_id' => Registry::factory()->create()->id,
        'title' => str_repeat('a', 192),
    ])->assertUnprocessable()->assertJsonValidationErrors('title');

    expect($quote->fresh()->opportunity_id)->toBe($origin->id)
        ->and($quote->fresh()->title)->toBe($title);
});

it('AC-015b: the Gestione Richieste route renames the offer and its panel exposes the title', function () {
    ['quote' => $quote] = registryTransferQuote();
    Sanctum::actingAs(registryTransferActor(['update', 'transferRegistry'], 'request-management'));

    $this->postJson("/api/request-management/{$quote->id}/transfer-registry", [
        'registry_id' => Registry::factory()->create()->id,
        'title' => 'Percorso orientamento',
    ])->assertOk();

    expect($quote->fresh()->title)->toBe('Percorso orientamento');

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Percorso orientamento');
});
