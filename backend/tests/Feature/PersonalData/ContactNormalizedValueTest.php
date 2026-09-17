<?php

use App\DataObjects\PersonalData\CreateContact;
use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * Spec 0136 AC-001: Contact::$normalized_value tracks type/value on every
 * save (ContactService is the sole write path — spec context), stays out of
 * default serialization and out of the activity log, and cannot be set by
 * mass assignment.
 */
beforeEach(function () {
    $this->service = app(ContactService::class);
    $this->card = PersonalData::factory()->create();
});

it('normalizes an email value on creation', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email,
        value: ' Mario@X.it ',
    ));

    expect($contact->fresh()->normalized_value)->toBe('mario@x.it');
});

it('normalizes a phone value on creation', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Phone,
        value: '333 123-4567',
    ));

    expect($contact->fresh()->normalized_value)->toBe('3331234567');
});

it('realigns normalized_value when value changes', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email,
        value: 'old@example.com',
    ));

    $this->service->update($contact, new CreateContact(
        type: ContactTypeEnum::Email,
        value: ' New@Example.com ',
    ));

    expect($contact->fresh()->normalized_value)->toBe('new@example.com');
});

it('realigns normalized_value when type changes', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email,
        value: '333 123 4567',
    ));

    expect($contact->fresh()->normalized_value)->toBe('333 123 4567');

    $this->service->update($contact, new CreateContact(
        type: ContactTypeEnum::Phone,
        value: '333 123 4567',
    ));

    expect($contact->fresh()->normalized_value)->toBe('3331234567');
});

it('does not expose normalized_value in toArray', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email,
        value: 'ada@example.com',
    ));

    expect($contact->fresh()->toArray())->not->toHaveKey('normalized_value')
        ->and($contact->fresh()->toArray())->not->toHaveKey('value');
});

it('does not log normalized_value in the activity log', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email,
        value: 'ada@example.com',
    ));

    $activity = Activity::query()
        ->where('log_name', 'contacts')
        ->where('subject_id', $contact->id)
        ->where('description', 'created')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    $changed = $activity->changes()['attributes'];

    expect($changed)->not->toHaveKey('normalized_value')
        ->and($changed)->not->toHaveKey('value');
});

it('ignores an attempt to mass-assign normalized_value', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email,
        value: 'ada@example.com',
    ));

    $expected = $contact->fresh()->normalized_value;

    $contact->fill(['normalized_value' => 'spoofed@example.com'])->save();

    expect($contact->fresh()->normalized_value)->toBe($expected)
        ->and($contact->fresh()->normalized_value)->not->toBe('spoofed@example.com');
});

it('resolves normalized_value to null for a raw type outside the enum', function () {
    // A legacy row with an invalid `type` can only exist via a raw DB insert
    // (Eloquent's enum-cast setter throws on write for any value outside
    // ContactTypeEnum — see the migration backfill test for that path) and,
    // once loaded, ANY subsequent save() crashes in Spatie's activity logger
    // reading the casted `type` (pre-existing, orthogonal to this hook). The
    // guard is exercised directly via reflection instead of a save() round
    // trip that cannot succeed regardless of this change.
    $contact = new Contact;
    $contact->setRawAttributes([
        'type' => 'legacy_channel',
        'value' => 'something',
    ]);

    $resolve = new ReflectionMethod(Contact::class, 'resolveNormalizedValue');
    $resolve->setAccessible(true);

    expect($resolve->invoke(null, $contact))->toBeNull();
});
