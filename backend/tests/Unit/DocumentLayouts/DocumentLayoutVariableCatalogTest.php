<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Enums\DocumentLayoutModule;
use App\Models\Attribute;
use App\Models\CustomFieldDefinition;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// spec 0069 — DocumentLayoutVariableCatalog (AC-040..AC-045; AC-045's HTTP
// concerns — missing/invalid `module`, 403 without `viewAny` — belong to the
// wave-2 controller/FormRequest, not this catalogue class, so they are not
// duplicated here).

if (! function_exists('dlCatalog')) {
    function dlCatalog(): DocumentLayoutVariableCatalog
    {
        return app(DocumentLayoutVariableCatalog::class);
    }

    /**
     * @param  array<string, mixed>|null  $matrixRow
     */
    function dlActorWithFieldPermissionRole(?array $matrixRow = null): User
    {
        $role = Role::create(['name' => 'dl-variable-catalog-role-'.uniqid()]);

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-040 — frozen categories, non-empty entries, no duplicates
// ---------------------------------------------------------------------------

it('contains every frozen category, in order, for the quotes module (AC-040)', function () {
    $categories = dlCatalog()->categoriesFor(DocumentLayoutModule::Quotes, User::factory()->create());

    expect(collect($categories)->pluck('key')->all())->toBe([
        'quote', 'totals', 'client', 'opportunity', 'referent', 'commercial', 'reporter',
        'supervisor', 'company', 'company_site', 'operational_site', 'custom_fields',
        'quote_attributes', 'document',
    ]);
});

it('every variable has a non-empty variable/label/type/example, and no variable is duplicated (AC-040)', function () {
    CustomFieldDefinition::factory()->forEntity('quotes')->create(['key' => 'delivery_notes', 'label' => 'Delivery notes']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'floor_size']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Quote->value]);

    $categories = dlCatalog()->categoriesFor(DocumentLayoutModule::Quotes, User::factory()->create());
    $variables = collect($categories)->flatMap(fn (array $category): array => $category['variables']);

    expect($variables)->not->toBeEmpty();

    foreach ($variables as $variable) {
        expect($variable['variable'])->not->toBe('')
            ->and($variable['label'])->not->toBe('')
            ->and($variable['type'])->not->toBe('')
            ->and($variable['example'])->not->toBe('');
    }

    $tokens = $variables->pluck('variable');
    expect($tokens->unique()->count())->toBe($tokens->count());
});

// ---------------------------------------------------------------------------
// AC-041 — no payment_method category, no discount variable (D-3/D-4)
// ---------------------------------------------------------------------------

it('has no payment_method category and no variable whose name contains "discount" (AC-041)', function () {
    $categories = dlCatalog()->categoriesFor(DocumentLayoutModule::Quotes, User::factory()->create());

    expect(collect($categories)->pluck('key')->all())->not->toContain('payment_method');

    $tokens = collect($categories)->flatMap(fn (array $category): array => $category['variables'])->pluck('variable');

    foreach ($tokens as $token) {
        expect($token)->not->toContain('discount');
    }
});

// ---------------------------------------------------------------------------
// AC-042 — custom_fields is dynamic
// ---------------------------------------------------------------------------

it('exposes an active custom field of quotes as {custom_fields.KEY} with zero code change (AC-042)', function () {
    CustomFieldDefinition::factory()->forEntity('quotes')->create(['key' => 'delivery_notes', 'label' => 'Delivery notes', 'type' => 'text']);
    CustomFieldDefinition::factory()->forEntity('quotes')->inactive()->create(['key' => 'archived_field', 'label' => 'Archived']);
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'other_entity_field', 'label' => 'Other entity']);

    $categories = dlCatalog()->categoriesFor(DocumentLayoutModule::Quotes, User::factory()->create());
    $tokens = collect(collect($categories)->firstWhere('key', 'custom_fields')['variables'])->pluck('variable');

    expect($tokens->all())->toContain('{custom_fields.delivery_notes}')
        ->and($tokens->all())->not->toContain('{custom_fields.archived_field}', '{custom_fields.other_entity_field}');
});

// ---------------------------------------------------------------------------
// AC-043 — quote_attributes is dynamic (spec 0084: was opportunity_attributes)
// ---------------------------------------------------------------------------

it('exposes an Attribute assigned in the quote context as {quote_attributes.CODE} (AC-043)', function () {
    $category = ProductCategory::factory()->create();
    $quoteAttribute = Attribute::factory()->create(['code' => 'floor_size', 'name' => 'Floor size']);
    $productOnlyAttribute = Attribute::factory()->create(['code' => 'weight_kg', 'name' => 'Weight']);

    $category->attributes()->attach($quoteAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Quote->value]);
    $category->attributes()->attach($productOnlyAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Product->value]);

    $categories = dlCatalog()->categoriesFor(DocumentLayoutModule::Quotes, User::factory()->create());
    $tokens = collect(collect($categories)->firstWhere('key', 'quote_attributes')['variables'])->pluck('variable');

    expect($tokens->all())->toContain('{quote_attributes.floor_size}')
        ->and($tokens->all())->not->toContain('{quote_attributes.weight_kg}');
});

// ---------------------------------------------------------------------------
// AC-044 — D-6 PII masking on {client.tax_code}
// ---------------------------------------------------------------------------

it('omits {client.tax_code} when role_field_permissions hides personal_data.tax_code, keeping every other client token (AC-044)', function () {
    $actor = dlActorWithFieldPermissionRole(['resource' => 'registries', 'field' => 'personal_data.tax_code', 'visible' => false, 'editable' => false, 'required' => false]);

    $categories = dlCatalog()->categoriesFor(DocumentLayoutModule::Quotes, $actor);
    $tokens = collect(collect($categories)->firstWhere('key', 'client')['variables'])->pluck('variable')->all();

    expect($tokens)->not->toContain('{client.tax_code}')
        ->and($tokens)->toContain('{client.name}', '{client.full_name}', '{client.type}', '{client.vat_number}', '{client.sdi_code}', '{client.email}', '{client.phone}');
});

it('a privileged (super-admin) actor sees {client.tax_code} regardless of role_field_permissions (AC-044)', function () {
    Role::findOrCreate('super-admin');
    $role = Role::create(['name' => 'dl-privileged-with-restriction']);
    $role->fieldPermissions()->create(['resource' => 'registries', 'field' => 'personal_data.tax_code', 'visible' => false, 'editable' => false, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole('super-admin');
    $actor->assignRole($role->name);

    $categories = dlCatalog()->categoriesFor(DocumentLayoutModule::Quotes, $actor);
    $tokens = collect(collect($categories)->firstWhere('key', 'client')['variables'])->pluck('variable')->all();

    expect($tokens)->toContain('{client.tax_code}');
});

it('when nothing hides personal_data.tax_code, an ordinary actor sees {client.tax_code} too (AC-044 baseline)', function () {
    $actor = dlActorWithFieldPermissionRole();

    $categories = dlCatalog()->categoriesFor(DocumentLayoutModule::Quotes, $actor);
    $tokens = collect(collect($categories)->firstWhere('key', 'client')['variables'])->pluck('variable')->all();

    expect($tokens)->toContain('{client.tax_code}');
});
