<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// POST /api/request-management/transfer (spec 0079, migrated onto the Quote
// by spec 0086 AC-034/AC-035): the write itself, authorization, D-3 scope,
// payload validation and Activity Log — AC-001 -> AC-012. `is_transferred`/
// `transferred_from_operational_site_id`/`operational_site_id` are now Quote
// columns (D-6), the audit trail stays anchored on the Opportunity (D-9).
// Notifications: RequestContactTransferNotificationTest.
// Resource/grid/no-write-path/site-deletion: RequestContactTransferGridTest.

uses(RefreshDatabase::class);

if (! function_exists('transferActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function transferActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'transferContact', 'export'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('transferSite')) {
    function transferSite(): OperationalSite
    {
        return OperationalSite::factory()->withAddress()->create();
    }
}

if (! function_exists('transferRequestManagedBy')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function transferRequestManagedBy(User $operator, array $attributes = []): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->attach($operator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id, ...$attributes]);
    }
}

// ---------------------------------------------------------------------------
// AC-001 / AC-002 / AC-003 / AC-004 / AC-005 — the write itself
// ---------------------------------------------------------------------------

it('transfers a single request: Sede, GA2 operator and is_transferred all change (AC-001)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $originSite = transferSite();
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $quote = Quote::factory()->create(['operational_site_id' => $originSite->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk()->assertJsonPath('data.transferred', 1);

    $quote->refresh();
    // Spec 0087, D-9/D-14: writes `quotes.operator_id`, never
    // `quotes.supervisor_id`. D-13: promoted onto the Opportunity's first
    // FREE slot — born with zero managers here, so slot 1, not the GA2 slot
    // `operatorManager()` reads.
    expect($quote->operational_site_id)->toBe($destinationSite->id)
        ->and($quote->operator_id)->toBe($newOperator->id)
        ->and($quote->supervisor_id)->toBeNull()
        ->and($quote->opportunity->operatorManager())->toBeNull()
        ->and($quote->is_transferred)->toBeTrue();
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $quote->opportunity_id,
        'user_id' => $newOperator->id,
        'position' => 1,
    ]);
});

it('records the ORIGIN Sede the request had before the transfer (AC-002)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $originSite = transferSite();
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $quote = Quote::factory()->create(['operational_site_id' => $originSite->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    expect($quote->fresh()->transferred_from_operational_site_id)->toBe($originSite->id);
});

it('a request with no Sede of origin transfers fine: origin stays null, is_transferred becomes true (AC-003)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $quote = Quote::factory()->create(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    $quote->refresh();
    expect($quote->transferred_from_operational_site_id)->toBeNull()
        ->and($quote->is_transferred)->toBeTrue();
});

it('every other relation of the request survives the transfer untouched — registry, referent, product_lines, products_of_interest, rewards, quotes, note, allegati and the other manager slots; the SIBLING offer is_transferred stays false (AC-004/AC-035)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $referent = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'general_notes' => 'keep me',
        'referent_id' => $referent->id,
    ]);
    $productLine = OpportunityProductLine::factory()->for($opportunity)->create();
    $product = Product::factory()->create();
    $opportunity->productsOfInterest()->attach($product->id);
    $reward = Reward::factory()->for($opportunity, 'source')->create();
    $sisterQuote = Quote::factory()->for($opportunity)->create();
    $note = Note::factory()->for($opportunity, 'notable')->create();
    $attachment = Attachment::factory()->for($opportunity, 'attachable')->create(['collection' => 'documents']);
    $otherManager = User::factory()->create();
    $opportunity->managers()->attach($otherManager->id, ['position' => 1]);
    $registryId = $opportunity->registry_id;
    $quote = Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    $opportunity->refresh();
    expect($opportunity->registry_id)->toBe($registryId)
        ->and($opportunity->referent_id)->toBe($referent->id)
        ->and($opportunity->general_notes)->toBe('keep me')
        ->and($opportunity->productLines()->whereKey($productLine->id)->exists())->toBeTrue()
        ->and($opportunity->productsOfInterest()->whereKey($product->id)->exists())->toBeTrue()
        ->and(Reward::query()->whereKey($reward->id)->exists())->toBeTrue()
        ->and(Quote::query()->whereKey($sisterQuote->id)->exists())->toBeTrue()
        ->and(Note::query()->whereKey($note->id)->exists())->toBeTrue()
        ->and(Attachment::query()->whereKey($attachment->id)->exists())->toBeTrue()
        ->and($opportunity->managers()->wherePivot('position', 1)->first()?->id)->toBe($otherManager->id)
        // AC-035: the flag is per-OFFERTA (D-6) — a sibling untouched by this
        // transfer never flips, even though it shares the same Opportunity.
        ->and($sisterQuote->fresh()->is_transferred)->toBeFalse();
});

it('transfers every selected request in one call and reports how many (AC-005)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $first = Quote::factory()->create();
    $second = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$first->id, $second->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk()->assertJsonPath('data.transferred', 2);

    expect($first->fresh()->is_transferred)->toBeTrue()
        ->and($second->fresh()->is_transferred)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-006 / AC-007 / AC-008 — authorization and D-3 scope
// ---------------------------------------------------------------------------

it('403 without request-management.transferContact, no write (AC-006)', function () {
    $actor = transferActorWith(['update', 'viewAll']);
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertForbidden();

    expect($quote->fresh()->is_transferred)->toBeFalse();
});

it('403 without request-management.update, no write (AC-007)', function () {
    $actor = transferActorWith(['viewAll', 'transferContact']);
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertForbidden();

    expect($quote->fresh()->is_transferred)->toBeFalse();
});

it('an out-of-scope request is silently skipped, never 403/404 on the batch (AC-008)', function () {
    $actor = transferActorWith(['update', 'transferContact']);
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $ownRequest = transferRequestManagedBy($actor);
    $outOfScope = transferRequestManagedBy(User::factory()->create());
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$ownRequest->id, $outOfScope->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk()->assertJsonPath('data.transferred', 1);

    expect($ownRequest->fresh()->is_transferred)->toBeTrue()
        ->and($outOfScope->fresh()->is_transferred)->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-009 / AC-010 — payload validation
// ---------------------------------------------------------------------------

it('422 when operator_id is absent from the payload (AC-009)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $destinationSite = transferSite();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
    ])->assertStatus(422)->assertJsonValidationErrors('operator_id');

    expect($quote->fresh()->is_transferred)->toBeFalse();
});

it('422 when operational_site_id or operator_id do not exist (AC-010)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => 999999,
        'operator_id' => 999999,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['operational_site_id', 'operator_id']);
});

// ---------------------------------------------------------------------------
// AC-011 / AC-012 — Activity Log (still anchored on the Opportunity, D-9)
// ---------------------------------------------------------------------------

it('writes ONE explicit Activity Log entry per transfer, causer = actor, Sede + operator in old/attributes (AC-011)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $originSite = transferSite();
    $destinationSite = transferSite();
    $newOperator = User::factory()->create();
    $quote = Quote::factory()->create(['operational_site_id' => $originSite->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $destinationSite->id,
        'operator_id' => $newOperator->id,
    ])->assertOk();

    $opportunity = $quote->opportunity;
    $entries = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('description', 'Request management contact transfer')
        ->get();

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();
    expect($entry->causer_id)->toBe($actor->id)
        ->and($entry->properties['attributes']['operational_site_id'])->toBe($destinationSite->id)
        ->and($entry->properties['attributes']['operator_id'])->toBe($newOperator->id)
        ->and($entry->properties['old']['operational_site_id'])->toBe($originSite->id);
});

it('two successive transfers leave TWO distinct Activity Log entries (AC-012)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $firstSite = transferSite();
    $secondSite = transferSite();
    $firstOperator = User::factory()->create();
    $secondOperator = User::factory()->create();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $firstSite->id,
        'operator_id' => $firstOperator->id,
    ])->assertOk();

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $secondSite->id,
        'operator_id' => $secondOperator->id,
    ])->assertOk();

    $opportunity = $quote->opportunity;
    $count = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('description', 'Request management contact transfer')
        ->count();

    expect($count)->toBe(2);
});

it('transferring to the ALREADY current Sede and operator is not a silent no-op: is_transferred flips true, ONE log entry with operator_id present (IDEMPOTENZA, spec 0079 <service>)', function () {
    $actor = transferActorWith(['update', 'viewAll', 'transferContact']);
    $site = transferSite();
    $operator = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->attach($operator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
    $quote = Quote::factory()->for($opportunity)->create(['operational_site_id' => $site->id, 'operator_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/transfer', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $site->id,
        'operator_id' => $operator->id,
    ])->assertOk()->assertJsonPath('data.transferred', 1);

    $quote->refresh();
    // The transfer targets the SAME operator already assigned: apply()'s
    // early-return means the writer never runs, so the Opportunity's
    // pre-existing GA2 (set up above) is untouched either way.
    expect($quote->is_transferred)->toBeTrue()
        ->and($quote->operator_id)->toBe($operator->id)
        ->and($quote->supervisor_id)->toBeNull()
        ->and($quote->opportunity->operatorManager()?->id)->toBe($operator->id);

    $entries = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('description', 'Request management contact transfer')
        ->get();

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();
    expect($entry->properties['attributes']['operator_id'])->toBe($operator->id)
        ->and($entry->properties['old']['operator_id'])->toBe($operator->id)
        ->and($entry->properties['attributes']['operational_site_id'])->toBe($site->id)
        ->and($entry->properties['attributes']['is_transferred'])->toBeTrue();
});
