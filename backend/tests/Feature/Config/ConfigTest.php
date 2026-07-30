<?php

use App\Enums\ContactTypeEnum;
use App\Enums\NotificationLevelEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

it('is public: returns 200 without authentication', function () {
    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'OK');
});

it('exposes data.enums with the allowlisted snake_case keys', function () {
    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'enums' => [
                    'locale',
                    'personal_data_type',
                    'contact_type',
                    'notification_level',
                    'referent_contact_scope',
                    'site_type',
                    'agreement_status',
                    'size_class',
                ],
            ],
        ]);
});

it('exposes the supported locales with native, locale-independent labels', function () {
    // Native names so a language picker shows each language in its own name; they
    // are NOT translated by Accept-Language (unlike the other enum labels).
    $this->getJson('/api/config', ['Accept-Language' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.enums.locale', [
            ['value' => 'en', 'label' => 'English', 'color' => null, 'icon' => null, 'is_default' => true, 'hidden_on_form' => false],
            ['value' => 'it', 'label' => 'Italiano', 'color' => null, 'icon' => null, 'is_default' => false, 'hidden_on_form' => false],
        ]);
});

it('serializes every enum option with the six EnumMeta snake_case keys', function () {
    $option = $this->getJson('/api/config')
        ->assertOk()
        ->json('data.enums.notification_level.0');

    expect($option)->toHaveKeys([
        'value', 'label', 'color', 'icon', 'is_default', 'hidden_on_form',
    ]);

    expect($option)->toMatchArray([
        'value' => 'info',
        'label' => 'Info',
        'color' => 'blue',
        'icon' => 'info',
        'is_default' => true,
        'hidden_on_form' => false,
    ]);
});

it('preserves enum declaration order', function () {
    $values = collect(
        $this->getJson('/api/config')->assertOk()->json('data.enums.contact_type')
    )->pluck('value')->all();

    expect($values)->toBe(['phone', 'mobile', 'fax', 'email', 'pec', 'website']);
});

it('exposes the expected option count per enum', function () {
    $enums = $this->getJson('/api/config')->assertOk()->json('data.enums');

    expect($enums['locale'])->toHaveCount(2)
        ->and($enums['personal_data_type'])->toHaveCount(2)
        ->and($enums['contact_type'])->toHaveCount(6)
        ->and($enums['notification_level'])->toHaveCount(4)
        ->and($enums['referent_contact_scope'])->toHaveCount(2)
        ->and($enums['site_type'])->toHaveCount(4)
        ->and($enums['agreement_status'])->toHaveCount(3)
        ->and($enums['size_class'])->toHaveCount(4);
});

// ---------------------------------------------------------------------------
// AC-018 (spec 0016) — referent_contact_scope options
// ---------------------------------------------------------------------------

it('exposes data.enums.referent_contact_scope with internal/external, internal default', function () {
    $options = $this->getJson('/api/config')->assertOk()->json('data.enums.referent_contact_scope');

    expect(collect($options)->pluck('value')->all())->toBe(['internal', 'external'])
        ->and(collect($options)->firstWhere('value', 'internal')['is_default'])->toBeTrue()
        ->and(collect($options)->firstWhere('value', 'external')['is_default'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-001 (spec 0020) — site_type / agreement_status / size_class options
// ---------------------------------------------------------------------------

it('exposes data.enums.site_type with legal_seat/delivery/billing/operational_site, billing default', function () {
    $options = $this->getJson('/api/config')->assertOk()->json('data.enums.site_type');

    expect(collect($options)->pluck('value')->all())
        ->toBe(['legal_seat', 'delivery', 'billing', 'operational_site'])
        ->and(collect($options)->firstWhere('value', 'billing')['is_default'])->toBeTrue()
        ->and(collect($options)->firstWhere('value', 'legal_seat')['is_default'])->toBeFalse();
});

it('exposes data.enums.agreement_status with negotiating/rejected/agreed, negotiating default', function () {
    $options = $this->getJson('/api/config')->assertOk()->json('data.enums.agreement_status');

    expect(collect($options)->pluck('value')->all())
        ->toBe(['negotiating', 'rejected', 'agreed'])
        ->and(collect($options)->firstWhere('value', 'negotiating')['is_default'])->toBeTrue();
});

it('exposes data.enums.size_class with micro/small/medium/large and no default', function () {
    $options = $this->getJson('/api/config')->assertOk()->json('data.enums.size_class');

    expect(collect($options)->pluck('value')->all())->toBe(['micro', 'small', 'medium', 'large'])
        ->and(collect($options)->pluck('is_default')->unique()->all())->toBe([false]);
});

it('filters out cases flagged hiddenOnForm', function () {
    // No domain enum case is currently hiddenOnForm, so the contract is: every
    // option returned reports hidden_on_form === false and the count matches the
    // full set of cases (nothing dropped today, but the filter is exercised).
    $data = $this->getJson('/api/config')->assertOk()->json('data.enums.notification_level');

    expect(collect($data)->pluck('hidden_on_form')->unique()->all())->toBe([false])
        ->and($data)->toHaveCount(count(NotificationLevelEnum::cases()));
});

it('stays aligned with the in-memory options() filtered on hiddenOnForm', function () {
    $expected = collect(ContactTypeEnum::options())
        ->reject->hiddenOnForm
        ->map->toArray()
        ->values()
        ->all();

    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('data.enums.contact_type', $expected);
});

it('localizes labels to the supported locale from Accept-Language', function () {
    $this->getJson('/api/config', ['Accept-Language' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.enums.contact_type.0.label', 'Telefono');
});

it('honours a weighted/region Accept-Language header', function () {
    $this->getJson('/api/config', ['Accept-Language' => 'it-IT,it;q=0.9,en;q=0.8'])
        ->assertOk()
        ->assertJsonPath('data.enums.contact_type.0.label', 'Telefono');
});

it('falls back to the app locale when Accept-Language is absent', function () {
    config(['app.locale' => 'en']);

    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('data.enums.contact_type.0.label', 'Phone');
});

it('falls back to the app locale on an unsupported or malicious Accept-Language', function () {
    config(['app.locale' => 'en']);

    foreach (['fr', 'zz-ZZ', '../../etc/passwd', '<script>', 'de;q=1'] as $header) {
        $this->getJson('/api/config', ['Accept-Language' => $header])
            ->assertOk()
            ->assertJsonPath('data.enums.contact_type.0.label', 'Phone');
    }

    // The locale resolution must never leave the app in an unsupported state.
    expect(App::getLocale())->toBe('en');
});

it('is no longer rate-limited', function () {
    $this->getJson('/api/config')
        ->assertOk()
        ->assertHeaderMissing('X-RateLimit-Limit');
});

it('no longer exposes the removed per-enum endpoint', function () {
    $this->getJson('/api/enums/contact-type')->assertNotFound();
    $this->getJson('/api/enums/notification-level')->assertNotFound();
});

// ---------------------------------------------------------------------------
// National mode — data.localization.default_country_iso2 (config/geo.php)
// ---------------------------------------------------------------------------

it('exposes data.localization.default_country_iso2 alongside the enums', function () {
    config(['geo.default_country_iso2' => 'IT']);

    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonStructure(['data' => ['enums', 'localization' => ['default_country_iso2']]])
        ->assertJsonPath('data.localization.default_country_iso2', 'IT');
});

it('normalizes the configured country code to uppercase', function () {
    config(['geo.default_country_iso2' => ' it ']);

    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('data.localization.default_country_iso2', 'IT');
});

it('reports null in international mode (code unset or blank)', function () {
    foreach ([null, '', '   '] as $configured) {
        config(['geo.default_country_iso2' => $configured]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.localization.default_country_iso2', null);
    }
});

it('reads the country code from the DEFAULT_COUNTRY_ISO2 env binding', function () {
    // The value must reach the payload through config/geo.php, not a literal.
    expect(config('geo.default_country_iso2'))->toBe(env('DEFAULT_COUNTRY_ISO2'));
});
