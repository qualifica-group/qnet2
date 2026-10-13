<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Legacy quote fixtures live in tests/Helpers/QuoteMigrationHelpers.php.

if (! function_exists('legacyQuoteAttribute')) {
    /**
     * A QUOTE-context attribute linked to the category, with its enum options.
     *
     * @param  array<string, mixed>  $extra
     * @param  array<int, string>  $options
     */
    function legacyQuoteAttribute(ProductCategory $category, string $code, string $type, array $extra = [], array $options = []): Attribute
    {
        $attribute = Attribute::factory()->create(['code' => $code, 'type' => $type, ...$extra]);

        foreach ($options as $index => $value) {
            $attribute->options()->create(['value' => $value, 'label' => "Option {$value}", 'sort_order' => $index]);
        }

        $category->attributes()->attach($attribute->id, [
            'is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Quote->value,
        ]);

        return $attribute;
    }
}

beforeEach(function () {
    seedMigrationsConfig();
    Opportunity::factory()->create(['old_id' => 7]);
    $this->category = legacyQuoteServiceProduct(10)->category;
});

it('writes the legacy additional information applicable to the offer lines (G-17)', function () {
    legacyQuoteAttribute($this->category, 'application_filed', 'textarea');
    legacyQuoteAttribute($this->category, 'urgent', 'enum', options: ['1', '0']);
    legacyQuoteAttribute($this->category, 'active_order', 'integer');
    legacyQuoteAttribute($this->category, 'data_endcertificate', 'date');
    legacyQuoteAttribute($this->category, 'categorie_mepa', 'enum', ['config' => ['display' => 'multiselect']], ['106', '127', '147']);
    legacyQuoteAttribute($this->category, 'pre_info_iso_addresses', 'table', ['config' => ['columns' => [
        ['key' => 'indirizzo', 'label' => 'Indirizzo', 'type' => 'text'],
        ['key' => 'request_certification', 'label' => 'Cert. Richiesta', 'type' => 'boolean'],
        ['key' => 'employees_per_site', 'label' => 'N. Add. per Siti', 'type' => 'integer'],
    ]]]);

    $run = runLegacyQuotesImport([legacyQuoteRecord(['attribute_values' => [
        'application_filed' => 'Assistenza meccanica',
        'urgent' => 1,
        'active_order' => '2',
        'data_endcertificate' => '2023-02-06',
        'categorie_mepa' => ['106', '147'],
        'pre_info_iso_addresses' => ['rows' => [
            ['indirizzo' => 'Via Roma 1', 'request_certification' => true, 'employees_per_site' => 4],
        ]],
    ]])]);

    $values = Quote::query()->where('old_id', 42)->sole()->attribute_values;

    expect($run->report)->toBeNull()
        ->and($values['application_filed'])->toBe('Assistenza meccanica')
        ->and($values['urgent'])->toBe('1')
        ->and($values['active_order'])->toBe(2)
        ->and($values['data_endcertificate'])->toBe('2023-02-06')
        ->and($values['categorie_mepa'])->toBe(['106', '147'])
        ->and($values['pre_info_iso_addresses']['rows'])->toHaveCount(1)
        ->and($values['pre_info_iso_addresses']['rows'][0])->toMatchArray([
            'indirizzo' => 'Via Roma 1', 'request_certification' => true, 'employees_per_site' => 4,
        ]);
});

it('remaps a legacy user via old_id and skips an unmigrated one with a warning', function () {
    $operator = User::factory()->create(['old_id' => 55]);
    $relation = ['relation_target' => ['entity_type' => 'users', 'cardinality' => 'one']];
    legacyQuoteAttribute($this->category, 'ps_user_id', 'relation', $relation);
    legacyQuoteAttribute($this->category, 'responsabile_progetto', 'relation', $relation);

    $run = runLegacyQuotesImport([legacyQuoteRecord(['attribute_values' => [
        'ps_user_id' => 55,
        'responsabile_progetto' => 99,
    ]])]);

    $values = Quote::query()->where('old_id', 42)->sole()->attribute_values;

    expect($values)->toBe(['ps_user_id' => $operator->id])
        ->and(legacyQuoteWarnings($run))->toContain('Legacy additional information responsabile_progetto: unresolved legacy id(s) 99.');
});

it('skips non-applicable codes and invalid values with a warning, keeping the quote and the other values', function () {
    legacyQuoteAttribute($this->category, 'newente', 'text');
    legacyQuoteAttribute($this->category, 'urgent', 'enum', options: ['1', '0']);

    $run = runLegacyQuotesImport([legacyQuoteRecord(['attribute_values' => [
        'newente' => 'URSS',
        'urgent' => 7,
        'grp2_surface_mq' => 120,
        'servizio_soa' => 1,
    ]])]);

    $warnings = legacyQuoteWarnings($run);

    expect($run->created_rows)->toBe(1)
        ->and(Quote::query()->where('old_id', 42)->sole()->attribute_values)->toBe(['newente' => 'URSS'])
        ->and($warnings)->toContain('Legacy additional information not applicable to the offer categories, not imported: grp2_surface_mq, servizio_soa.')
        ->and(collect($warnings)->contains(fn (string $warning): bool => str_starts_with($warning, 'Legacy additional information urgent not imported:')))->toBeTrue();
});

it('gives every offer of the same opportunity the same additional information', function () {
    legacyQuoteAttribute($this->category, 'newente', 'text');

    runLegacyQuotesImport([
        legacyQuoteRecord(['id' => 42, 'attribute_values' => ['newente' => 'URSS']]),
        legacyQuoteRecord(['id' => 43, 'lines' => [
            ['id' => 901, 'product_id' => 10, 'quantity' => 1, 'unit_price' => 50, 'commissions' => []],
        ], 'attribute_values' => ['newente' => 'URSS']]),
    ]);

    expect(Quote::query()->orderBy('old_id')->pluck('attribute_values')->all())
        ->toBe([['newente' => 'URSS'], ['newente' => 'URSS']]);
});
