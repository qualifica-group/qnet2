<?php

use App\DataObjects\Enums\EnumMeta;
use App\Enums\Attributes\HiddenOnForm;
use App\Enums\Attributes\Percentage;
use App\Enums\Concerns\HasMeta;
use App\Enums\ContactTypeEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\PersonalDataTypeEnum;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

// These tests exercise translation (__()) and the App facade, so they need the
// Laravel application bootstrapped. The default Pest binding only applies the
// full TestCase to the Feature suite, so bind it explicitly here.
uses(TestCase::class);

/**
 * Fixture enum exercising the edge cases not covered by the four domain enums:
 * a case with no #[Label] (humanized fallback), a #[HiddenOnForm] case, and a
 * case decorated with an unrelated attribute that the reader must ignore.
 */
enum MetaFixtureEnum: string
{
    use HasMeta;

    // No #[Label] → label() humanizes the case name to "Action Url".
    case ActionUrl = 'action_url';

    // No #[Label] → label() humanizes to "Plain"; also decorated with an
    // unrelated attribute the reader must ignore (no exception, no leak).
    #[Percentage(50)]
    case Plain = 'plain';

    #[HiddenOnForm(true)]
    case Hidden = 'hidden';
}

afterEach(function () {
    App::setLocale('en');
});

/*
|--------------------------------------------------------------------------
| label() — translation, fallback, locale switching
|--------------------------------------------------------------------------
*/

it('returns the translated label when a translation exists for the current locale', function () {
    App::setLocale('it');

    expect(ContactTypeEnum::Phone->label())->toBe('Telefono')
        ->and(NotificationLevelEnum::Error->label())->toBe('Errore')
        ->and(PersonalDataTypeEnum::Company->label())->toBe('Azienda');
});

it('returns the EN source label when no translation exists for the locale', function () {
    App::setLocale('en');

    expect(ContactTypeEnum::Phone->label())->toBe('Phone')
        ->and(NotificationLevelEnum::Info->label())->toBe('Info');
});

it('resolves the label on every read so a locale switch is honoured without cache clearing', function () {
    App::setLocale('en');
    expect(ContactTypeEnum::Email->label())->toBe('Email');

    App::setLocale('it');
    expect(ContactTypeEnum::Email->label())->toBe('Email');

    App::setLocale('en');
    expect(NotificationLevelEnum::Warning->label())->toBe('Warning');

    App::setLocale('it');
    expect(NotificationLevelEnum::Warning->label())->toBe('Avviso');
});

it('humanizes the case name as a label fallback when no #[Label] attribute is present', function () {
    expect(MetaFixtureEnum::ActionUrl->label())->toBe('Action Url')
        ->and(MetaFixtureEnum::Plain->label())->toBe('Plain');
});

/*
|--------------------------------------------------------------------------
| color() / icon() / isDefault() / hiddenOnForm() — present vs absent
|--------------------------------------------------------------------------
*/

it('returns attribute values when the attributes are present', function () {
    expect(NotificationLevelEnum::Info->color())->toBe('blue')
        ->and(NotificationLevelEnum::Info->icon())->toBe('info')
        ->and(NotificationLevelEnum::Info->isDefault())->toBeTrue()
        ->and(ContactTypeEnum::Phone->icon())->toBe('phone')
        ->and(ContactTypeEnum::Phone->isDefault())->toBeTrue();
});

it('returns null/false when the attributes are absent', function () {
    // ContactType cases carry no #[Color] and (except Phone) no #[IsDefault].
    expect(ContactTypeEnum::Fax->color())->toBeNull()
        ->and(ContactTypeEnum::Fax->isDefault())->toBeFalse()
        ->and(ContactTypeEnum::Fax->hiddenOnForm())->toBeFalse();
});

it('never throws for a case decorated with unrelated attributes and reads only the five presentation ones', function () {
    expect(MetaFixtureEnum::Plain->color())->toBeNull()
        ->and(MetaFixtureEnum::Plain->icon())->toBeNull()
        ->and(MetaFixtureEnum::Plain->isDefault())->toBeFalse()
        ->and(MetaFixtureEnum::Plain->hiddenOnForm())->toBeFalse();
});

it('honours #[HiddenOnForm(true)]', function () {
    expect(MetaFixtureEnum::Hidden->hiddenOnForm())->toBeTrue()
        ->and(MetaFixtureEnum::Plain->hiddenOnForm())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| meta() / toArray() — snake_case contract
|--------------------------------------------------------------------------
*/

it('builds an EnumMeta for the current case', function () {
    $meta = NotificationLevelEnum::Success->meta();

    expect($meta)->toBeInstanceOf(EnumMeta::class)
        ->and($meta->value)->toBe('success')
        ->and($meta->label)->toBe('Success')
        ->and($meta->color)->toBe('green')
        ->and($meta->icon)->toBe('check-circle')
        ->and($meta->isDefault)->toBeFalse()
        ->and($meta->hiddenOnForm)->toBeFalse();
});

it('serializes EnumMeta with snake_case keys', function () {
    $array = NotificationLevelEnum::Info->meta()->toArray();

    expect(array_keys($array))->toBe(['value', 'label', 'color', 'icon', 'is_default', 'hidden_on_form'])
        ->and($array['value'])->toBe('info')
        ->and($array['label'])->toBe('Info')
        ->and($array['color'])->toBe('blue')
        ->and($array['icon'])->toBe('info')
        ->and($array['is_default'])->toBeTrue()
        ->and($array['hidden_on_form'])->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| options() — order and type
|--------------------------------------------------------------------------
*/

it('returns options for every case in declaration order as EnumMeta', function () {
    $options = ContactTypeEnum::options();

    expect($options)->toHaveCount(5)
        ->and($options)->each->toBeInstanceOf(EnumMeta::class)
        ->and(array_map(fn (EnumMeta $m) => $m->value, $options))
        ->toBe(['phone', 'fax', 'email', 'pec', 'website']);
});

/*
|--------------------------------------------------------------------------
| default()
|--------------------------------------------------------------------------
*/

it('returns the #[IsDefault] case for enums that declare one', function () {
    expect(NotificationLevelEnum::default())->toBe(NotificationLevelEnum::Info)
        ->and(PersonalDataTypeEnum::default())->toBe(PersonalDataTypeEnum::Individual)
        ->and(ContactTypeEnum::default())->toBe(ContactTypeEnum::Phone);
});

/*
|--------------------------------------------------------------------------
| Consistency: #[IsDefault] case === fromValue(null)
|--------------------------------------------------------------------------
*/

it('keeps #[IsDefault] consistent with fromValue(null) for each domain enum', function () {
    expect(NotificationLevelEnum::default())->toBe(NotificationLevelEnum::fromValue(null))
        ->and(PersonalDataTypeEnum::default())->toBe(PersonalDataTypeEnum::fromValue(null))
        ->and(ContactTypeEnum::default())->toBe(ContactTypeEnum::fromValue(null));
});
