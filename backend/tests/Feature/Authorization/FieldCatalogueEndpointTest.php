<?php

use App\Authorization\RolesAuthorization;
use App\Authorization\UsersAuthorization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('actorWithRoleAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function actorWithRoleAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("roles.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("roles.{$ability}");
        }

        return $user;
    }
}

it('401 without auth', function () {
    $this->getJson('/api/authorization/fields')->assertUnauthorized();
});

it('403 without roles.create or roles.update', function () {
    $actor = actorWithRoleAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/authorization/fields')->assertForbidden();
});

it('200 with roles.create only', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/authorization/fields')->assertOk();
});

it('200 with roles.update only', function () {
    $actor = actorWithRoleAbilities(['update']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/authorization/fields')->assertOk();
});

it('200 with the catalogue for users and roles, keys matching each resolver\'s fields()', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/fields')
        ->assertOk()
        ->assertJsonPath('success', true);

    $resources = collect($response->json('data.resources'))->keyBy('resource');

    // spec 0010 registered `business-functions` AND `companies`, spec 0011
    // `operational-sites`, spec 0016 `referent-types` AND `referents`, spec
    // 0017 `attributes`, `product-categories` AND `products`, spec 0018
    // `sources`, `sectors` AND `company-sites`, spec 0019 `tags`, spec 0020
    // `registries`, spec 0021 `custom-fields`, spec 0023 `pipeline-statuses`,
    // `projects` AND `campaigns`, spec 0024 `leads`, spec 0040
    // `opportunities`, spec 0047 registered the workflow configurator (since
    // renamed, spec 0083 D-6, see below),
    // `vat-rates` (VAT-rate lookup for products), all in the same generic
    // registry (config/authorization.php), so this registry-driven catalogue
    // legitimately grows to include them. `import-runs` is NOT here: its meta
    // definition was removed (2026-07-17) when the module collapsed onto
    // `leads.import` — an import run has no editable form. Spec 0083, D-6:
    // the workflow configurator's resource moved onto the Offerta and is
    // renamed `quote-workflows`; the former flat quote-status module is
    // dropped entirely.
    expect($resources->keys()->all())->toEqualCanonicalizing([
        'users', 'roles', 'business-functions', 'companies', 'company-sites', 'operational-sites', 'referent-types',
        'referents', 'attributes', 'custom-fields', 'product-categories', 'products', 'sources', 'sectors', 'tags',
        'registries', 'pipeline-statuses', 'projects', 'campaigns', 'leads',
        'opportunities', 'quote-workflows', 'vat-rates',
        // spec 0049 `request-management` (RequestManagementAuthorization registered in the generic
        // registry so GET /api/meta/request-management works; the module operates on Opportunity).
        'request-management',
        // spec 0058 `reward-types` (RewardTypesAuthorization: the "Buoni, Premi e Incentivi" lookup
        // — name/color, both mandatory, D-5).
        'reward-types',
        // spec 0059 `rewarded-referents` (RewardedReferentsAuthorization: the "Referenti con Buoni"
        // aggregated, READ-ONLY view, D-6 — registered so GET /api/meta/rewarded-referents resolves,
        // but `fields()` is empty: the module has no dedicated write surface).
        'rewarded-referents',
        // spec 0060 `reward-statuses` (RewardStatusesAuthorization: the "Stati Buoni Collegati"
        // lookup — name/color mandatory, description/is_active optional, D-4).
        'reward-statuses',
        // spec 0065 `quotes` (QuotesAuthorization: the Quotes module resource).
        'quotes',
        // spec 0066 `commission-configurations` (independent commission-rule
        // catalogue with field-level permissions).
        'commission-configurations',
        // spec 0068 `payment-methods` (PaymentMethodsAuthorization: the
        // consumer-agnostic payment-modality lookup — name/code mandatory,
        // `code` writable only on create, D-3).
        'payment-methods',
        // spec 0069 `document-layouts` (DocumentLayoutsAuthorization: the
        // block-based document layout catalogue — name/code/module/config
        // mandatory, `code`/`module` writable only on create, D-2).
        'document-layouts',
        // spec 0072 `contract-statuses` AND `contracts`
        // (ContractStatusesAuthorization: the "Stati Contratto" lookup —
        // name/color mandatory, description/group/is_active/is_default
        // optional, BR-5; ContractsAuthorization: the Contracts module
        // resource, editable-field surface only, D-6 — no create/delete).
        'contract-statuses', 'contracts',
    ]);

    $userFieldKeys = collect($resources['users']['fields'])->pluck('key')->all();
    $expectedUserKeys = array_map(fn ($field) => $field->key, app(UsersAuthorization::class)->fields());
    expect($userFieldKeys)->toEqualCanonicalizing($expectedUserKeys);

    $roleFieldKeys = collect($resources['roles']['fields'])->pluck('key')->all();
    $expectedRoleKeys = array_map(fn ($field) => $field->key, app(RolesAuthorization::class)->fields());
    expect($roleFieldKeys)->toEqualCanonicalizing($expectedRoleKeys);

    // Spec 0008: `mandatory` is a new flag on every catalogue entry.
    foreach ($resources['users']['fields'] as $field) {
        expect($field)->toHaveKeys(['key', 'type', 'group', 'mandatory']);
    }
});

// ---------------------------------------------------------------------------
// AC-001 (spec 0008) — the catalogue's `users` entry carries the 4 existing
// keys AND the 13 personal_data.* keys, with the exact type/group contract.
// ---------------------------------------------------------------------------

it('spec 0008/0015: users.fields contains exactly the 4 existing + 13 personal_data.* + 12 employment.* keys, with the contracted type/group', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/fields')->assertOk();
    $usersFields = collect($response->json('data.resources'))->firstWhere('resource', 'users')['fields'];
    $byKey = collect($usersFields)->keyBy('key');

    expect($byKey->keys()->all())->toEqualCanonicalizing([
        'email', 'locale', 'is_active', 'roles', 'password',
        'personal_data.type', 'personal_data.first_name',
        'personal_data.last_name', 'personal_data.company_name', 'personal_data.tax_code',
        'personal_data.vat_number', 'personal_data.sdi_code', 'personal_data.birth_date',
        'personal_data.birth_city_id', 'personal_data.residence_city_id', 'personal_data.gender',
        'personal_data.contacts', 'personal_data.addresses',
        // spec 0015 — the 12 employment.* keys.
        'employment.is_manager', 'employment.job_description', 'employment.reports_to_id',
        'employment.business_function_id', 'employment.relationship_type', 'employment.company_id',
        'employment.operational_site_id', 'employment.qualification_type', 'employment.hired_at',
        'employment.terminated_at', 'employment.standard_daily_minutes', 'employment.break_daily_minutes',
    ]);

    $expectedTypes = [
        'personal_data.type' => 'select',
        'personal_data.first_name' => 'text',
        'personal_data.last_name' => 'text',
        'personal_data.company_name' => 'text',
        'personal_data.tax_code' => 'text',
        'personal_data.vat_number' => 'text',
        'personal_data.sdi_code' => 'text',
        'personal_data.birth_date' => 'date',
        'personal_data.birth_city_id' => 'select',
        'personal_data.gender' => 'select',
        'personal_data.contacts' => 'collection',
        'personal_data.addresses' => 'collection',
    ];

    foreach ($expectedTypes as $key => $type) {
        expect($byKey[$key]['type'])->toBe($type)
            ->and($byKey[$key]['group'])->toBe('personal_data');
    }
});

// ---------------------------------------------------------------------------
// Mandatory fields (spec 0008 follow-up) — the catalogue flags the fields
// vital to creating the resource; `email`/`personal_data.first_name`/`roles.name`
// are mandatory, `personal_data.tax_code` is not.
// ---------------------------------------------------------------------------

it('spec 0008: the catalogue flags mandatory fields — email/personal_data.first_name/roles.name true, personal_data.tax_code false', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/fields')->assertOk();
    $resources = collect($response->json('data.resources'))->keyBy('resource');

    $usersByKey = collect($resources['users']['fields'])->keyBy('key');
    expect($usersByKey['email']['mandatory'])->toBeTrue()
        ->and($usersByKey['personal_data.first_name']['mandatory'])->toBeTrue()
        ->and($usersByKey['personal_data.tax_code']['mandatory'])->toBeFalse();

    $rolesByKey = collect($resources['roles']['fields'])->keyBy('key');
    expect($rolesByKey['name']['mandatory'])->toBeTrue();
});
