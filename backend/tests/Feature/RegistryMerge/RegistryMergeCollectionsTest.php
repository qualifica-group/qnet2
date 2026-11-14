<?php

use App\Models\Address;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Note;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\RecordMerge;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Sanctum::actingAs(mergeSuperAdmin());
});

// AC-004 — discard, move, no duplicates, primaries

it('moves kept contacts and addresses, drops discarded ones and never duplicates', function () {
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    mergeContact($survivor, 'phone', '+39 02 1112223', true);
    mergeContact($duplicate, 'phone', '+39 02 1112223', true);
    $movedPhone = mergeContact($duplicate, 'phone', '+39 02 9998887', true);
    $movedEmail = mergeContact($duplicate, 'email', 'info@acme.test', true);
    $discardedEmail = mergeContact($duplicate, 'email', 'old@acme.test');

    $sameAddress = ['line1' => 'Via Roma 1', 'postal_code' => '00100', 'city_id' => null];
    $survivor->personalData->addresses()->create([...$sameAddress, 'is_primary' => true]);
    $duplicate->personalData->addresses()->create([...$sameAddress, 'is_primary' => true]);
    $keptAddress = $duplicate->personalData->addresses()->create(['line1' => 'Via Milano 2', 'postal_code' => '20100', 'is_primary' => true]);
    $discardedAddress = $duplicate->personalData->addresses()->create(['line1' => 'Via Napoli 3', 'postal_code' => '80100']);

    $this->postJson('/api/registries/merge', [
        'survivor_id' => $survivor->id,
        'merged_ids' => [$duplicate->id],
        'discard' => ['contacts' => [$discardedEmail->id], 'addresses' => [$discardedAddress->id]],
    ])->assertOk();

    $cardId = $survivor->personalData->id;
    $contacts = $survivor->personalData->contacts()->get();
    expect($contacts)->toHaveCount(3)
        ->and($movedPhone->fresh()->contactable_id)->toBe($cardId)
        ->and($movedPhone->fresh()->is_primary)->toBeFalse()
        ->and($movedEmail->fresh()->contactable_id)->toBe($cardId)
        ->and($movedEmail->fresh()->is_primary)->toBeTrue()
        ->and($contacts->where('is_primary', true)->where('type.value', 'phone'))->toHaveCount(1)
        ->and($contacts->first(fn ($c) => $c->value === '+39 02 1112223')->is_primary)->toBeTrue()
        ->and(Contact::query()->whereKey($discardedEmail->id)->exists())->toBeFalse();

    expect($survivor->personalData->addresses()->count())->toBe(2)
        ->and($keptAddress->fresh()->addressable_id)->toBe($cardId)
        ->and($keptAddress->fresh()->is_primary)->toBeFalse()
        ->and(Address::query()->whereKey($discardedAddress->id)->exists())->toBeFalse();
});

it('skips an element already moved from an earlier duplicate', function () {
    $survivor = mergeCompany('Acme Srl');
    $first = mergeCompany('Dup One');
    $second = mergeCompany('Dup Two');
    mergeContact($first, 'email', 'Same@Acme.test');
    $twin = mergeContact($second, 'email', 'same@acme.test');
    $survivor->personalData->addresses()->create(['line1' => 'Via  Roma 1', 'postal_code' => '00100']);
    $address = $first->personalData->addresses()->create(['line1' => 'via roma 1', 'postal_code' => '00100']);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$first->id, $second->id]])->assertOk();

    expect($survivor->personalData->contacts()->count())->toBe(1)
        ->and(Contact::query()->whereKey($twin->id)->exists())->toBeFalse()
        ->and($survivor->personalData->addresses()->count())->toBe(1)
        ->and(Address::query()->whereKey($address->id)->exists())->toBeFalse();
});

it('moves kept notes with their replies and soft-deletes a discarded root with its replies', function () {
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $kept = mergeNote($duplicate, 'Keep me');
    $reply = mergeNote($duplicate, 'A reply', $kept->id);
    $dropped = mergeNote($duplicate, 'Drop me');
    $droppedReply = mergeNote($duplicate, 'Dropped reply', $dropped->id);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id], 'discard' => ['notes' => [$dropped->id]]])->assertOk();

    expect($kept->fresh()->notable_id)->toBe($survivor->id)
        ->and($reply->fresh()->notable_id)->toBe($survivor->id)
        ->and($survivor->notes()->count())->toBe(2)
        ->and(Note::withTrashed()->find($dropped->id)->trashed())->toBeTrue()
        ->and(Note::withTrashed()->find($droppedReply->id)->trashed())->toBeTrue();
});

it('moves kept documents and deletes the discarded ones with the duplicate', function () {
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $kept = mergeDocument($duplicate, 'keep.pdf');
    $dropped = mergeDocument($duplicate, 'drop.pdf');

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id], 'discard' => ['documents' => [$dropped->id]]])->assertOk();

    expect($kept->fresh()->attachable_id)->toBe($survivor->id)
        ->and(Attachment::query()->whereKey($dropped->id)->exists())->toBeFalse();
});

// AC-005 — pivots and conflicts

it('unites sectors, referents and managers without duplicates and re-elects the primary referent', function () {
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    [$shared, $onlyDuplicate, $discarded] = Sector::factory()->count(3)->create();
    $survivor->sectors()->attach($shared);
    $duplicate->sectors()->attach([$shared->id, $onlyDuplicate->id, $discarded->id]);

    $referent = mergeReferent('Ada', 'Lovelace');
    $duplicate->referents()->attach($referent);
    $duplicate->forceFill(['primary_referent_id' => $referent->id])->save();

    [$keeper, $newcomer, $sharedManager] = User::factory()->count(3)->create();
    $survivor->managers()->attach($keeper, ['position' => 1]);
    $survivor->managers()->attach($sharedManager, ['position' => 3]);
    $duplicate->managers()->attach($sharedManager, ['position' => 1]);
    $duplicate->managers()->attach($newcomer, ['position' => 2]);

    $this->postJson('/api/registries/merge', [
        'survivor_id' => $survivor->id,
        'merged_ids' => [$duplicate->id],
        'discard' => ['sectors' => [$discarded->id]],
        'fields' => ['primary_referent_id' => $duplicate->id],
    ])->assertOk();

    $survivor->refresh();
    expect($survivor->sectors()->pluck('sectors.id')->sort()->values()->all())->toBe([$shared->id, $onlyDuplicate->id])
        ->and($survivor->referents()->pluck('referents.id')->all())->toBe([$referent->id])
        ->and($survivor->primary_referent_id)->toBe($referent->id)
        ->and($survivor->managers->mapWithKeys(fn ($u) => [$u->id => $u->pivot->position])->all())
        ->toBe([$keeper->id => 1, $newcomer->id => 2, $sharedManager->id => 3]);
});

it('refuses more managers than the positions available and changes nothing', function () {
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $max = ManagerPositions::MAX;

    foreach (User::factory()->count($max)->create() as $index => $user) {
        $survivor->managers()->attach($user, ['position' => $index + 1]);
    }
    $extra = User::factory()->create();
    $duplicate->managers()->attach($extra, ['position' => 1]);
    $contact = mergeContact($duplicate, 'email', 'info@acme.test');

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discard.managers']);

    expect($survivor->managers()->count())->toBe($max)
        ->and(Registry::query()->whereKey($duplicate->id)->exists())->toBeTrue()
        ->and($contact->fresh()->contactable_id)->toBe($duplicate->personalData->id)
        ->and(RecordMerge::query()->count())->toBe(0);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id], 'discard' => ['managers' => [$extra->id]]])->assertOk();
    expect($survivor->managers()->count())->toBe($max);
});

it('keeps the survivor product supplier row on a conflict and snapshots the dropped one', function () {
    $survivor = mergeCompany('Acme Srl', ['is_supplier' => true]);
    $duplicate = mergeCompany('Acme S.r.l.', ['is_supplier' => true]);
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $mine = ProductSupplier::factory()->create(['product_id' => $product->id, 'supplier_id' => $survivor->id, 'cost' => 10]);
    $conflicting = ProductSupplier::factory()->create(['product_id' => $product->id, 'supplier_id' => $duplicate->id, 'cost' => 99]);
    $moved = ProductSupplier::factory()->create(['product_id' => $otherProduct->id, 'supplier_id' => $duplicate->id]);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])->assertOk();

    expect(ProductSupplier::query()->whereKey($conflicting->id)->exists())->toBeFalse()
        ->and($mine->fresh()->supplier_id)->toBe($survivor->id)
        ->and($moved->fresh()->supplier_id)->toBe($survivor->id);

    $dropped = RecordMerge::query()->sole()->snapshot['dropped'];
    expect($dropped)->toHaveCount(1)
        ->and($dropped[0]['table'])->toBe('product_suppliers')
        ->and($dropped[0]['row']['id'])->toBe($conflicting->id);
});
