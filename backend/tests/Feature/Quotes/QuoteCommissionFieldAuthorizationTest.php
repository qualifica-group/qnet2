<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<string, array{visible: bool, editable: bool}>  $fieldPermissions
 */
function quoteCommissionFieldActor(array $fieldPermissions): User
{
    foreach (['view', 'update', 'viewActivity'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $role = Role::create(['name' => fake()->unique()->slug()]);
    $role->givePermissionTo(['quotes.view', 'quotes.update', 'quotes.viewActivity']);

    foreach ($fieldPermissions as $field => $permission) {
        $role->fieldPermissions()->create([
            'resource' => 'quotes',
            'field' => $field,
            'visible' => $permission['visible'],
            'editable' => $permission['editable'],
            'required' => false,
        ]);
    }

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

/**
 * @return array{quote: Quote, line: QuoteLine, commission: QuoteLineCommission, product: Product, commercial: Referent}
 */
function quoteWithManualCommission(): array
{
    $commercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create(['commercial_id' => $commercial->id]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'net_amount' => 100,
        'total_amount' => 100,
    ]);
    $commission = QuoteLineCommission::factory()->create([
        'quote_line_id' => $line->id,
        'recipient_role' => CommissionRecipientRole::Commercial,
        'recipient_type' => 'referent',
        'recipient_id' => $commercial->id,
        'value' => 5,
        'calculated_amount' => 5,
        'internal_note' => 'Sensitive service note',
        'origin' => CommissionOrigin::ManualOverride,
    ]);

    return compact('quote', 'line', 'commission', 'product', 'commercial');
}

it('redacts hidden commission fields from quote detail, defaults and activity', function () {
    $context = quoteWithManualCommission();
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $context['product']->id,
        'product_category_id' => null,
        'valid_from' => '2026-01-01',
        'internal_note' => 'Hidden default note',
    ]);
    $actor = quoteCommissionFieldActor([
        'commission_recipient' => ['visible' => false, 'editable' => false],
        'commission_value' => ['visible' => false, 'editable' => false],
        'commission_internal_note' => ['visible' => false, 'editable' => false],
    ]);
    Sanctum::actingAs($actor);

    $detail = $this->getJson("/api/quotes/{$context['quote']->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.summary.commissions')
        ->json('data.offer_lines.0.commissions.0');
    expect($detail)->not->toHaveKeys([
        'recipient_type', 'recipient_id', 'recipient', 'value',
        'calculated_amount', 'internal_note',
    ])->and($detail)->toHaveKeys(['recipient_role', 'commission_type', 'origin']);

    $default = $this->postJson('/api/quotes/commission-defaults', [
        'quote_id' => $context['quote']->id,
        'product_id' => $context['product']->id,
        'line_net_amount' => 100,
        'reference_date' => '2026-07-29',
    ])->assertOk()->json('data.0');
    expect($default)->not->toHaveKeys([
        'recipient_type', 'recipient_id', 'value', 'calculated_amount', 'internal_note',
    ])->and($default)->toHaveKeys(['recipient_role', 'commission_type', 'origin']);

    $activities = $this->getJson("/api/activity-log/quotes/{$context['quote']->id}")
        ->assertOk()
        ->json('data.items');
    $commissionFields = collect($activities)
        ->where('module', 'quote_line_commission')
        ->flatMap(fn (array $activity): array => collect($activity['changes'])->pluck('field')->all())
        ->all();

    expect($commissionFields)->not->toContain(
        'recipient_type', 'recipient_id', 'value', 'calculated_amount', 'internal_note',
    );
});

it('keeps readonly values visible and allows only an unchanged nested no-op', function () {
    $context = quoteWithManualCommission();
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $context['product']->id,
        'product_category_id' => null,
        'valid_from' => '2026-01-01',
        'value' => 5,
    ]);
    $actor = quoteCommissionFieldActor([
        'commission_value' => ['visible' => true, 'editable' => false],
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/quotes/{$context['quote']->id}")
        ->assertOk()
        ->assertJsonPath('data.offer_lines.0.commissions.0.value', '5.0000')
        ->assertJsonPath('permissions.fields.commission_value.readonly', true);

    $this->postJson('/api/quotes/commission-defaults', [
        'quote_id' => $context['quote']->id,
        'product_id' => $context['product']->id,
        'line_net_amount' => 100,
        'reference_date' => '2026-07-29',
    ])->assertOk()
        ->assertJsonPath('data.0.value', '5.0000')
        ->assertJsonPath('data.0.calculated_amount', '5.00');

    $activityFields = collect(
        $this->getJson("/api/activity-log/quotes/{$context['quote']->id}")
            ->assertOk()
            ->json('data.items'),
    )
        ->where('module', 'quote_line_commission')
        ->flatMap(fn (array $activity): array => collect($activity['changes'])->pluck('field')->all());
    expect($activityFields)->toContain('value', 'calculated_amount');

    $commission = [
        'id' => $context['commission']->id,
        'recipient_role' => 'COMMERCIAL',
        'recipient_type' => 'referent',
        'recipient_id' => $context['commercial']->id,
        'commission_type' => 'PERCENTAGE',
        'value' => '5.0000',
        'internal_note' => 'Sensitive service note',
        'origin' => 'MANUAL_OVERRIDE',
        'commission_configuration_id' => null,
    ];
    $line = [
        'id' => $context['line']->id,
        'product_id' => $context['product']->id,
        'quantity' => 1,
        'unit_price' => 100,
        'commissions' => [$commission],
    ];

    $this->patchJson("/api/quotes/{$context['quote']->id}", ['offer_lines' => [$line]])
        ->assertOk()
        ->assertJsonPath('data.offer_lines.0.commissions.0.value', '5.0000');

    $line['commissions'][0]['value'] = '6.0000';
    $this->patchJson("/api/quotes/{$context['quote']->id}", ['offer_lines' => [$line]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('offer_lines.0.commissions.0.value');

    expect($context['commission']->fresh()->value)->toBe('5.0000');
});

it('allows a no-op but rejects a semantic change when the commission collection is readonly', function () {
    $context = quoteWithManualCommission();
    $actor = quoteCommissionFieldActor([
        'commissions' => ['visible' => true, 'editable' => false],
    ]);
    Sanctum::actingAs($actor);

    $commission = [
        'id' => $context['commission']->id,
        'recipient_role' => 'COMMERCIAL',
        'recipient_type' => 'referent',
        'recipient_id' => $context['commercial']->id,
        'commission_type' => 'PERCENTAGE',
        'value' => '5.0000',
        'internal_note' => 'Sensitive service note',
        'origin' => 'MANUAL_OVERRIDE',
        'commission_configuration_id' => null,
    ];
    $line = [
        'id' => $context['line']->id,
        'product_id' => $context['product']->id,
        'quantity' => 1,
        'unit_price' => 100,
        'commissions' => [$commission],
    ];

    $this->patchJson("/api/quotes/{$context['quote']->id}", ['offer_lines' => [$line]])
        ->assertOk();

    $line['commissions'] = [];
    $this->patchJson("/api/quotes/{$context['quote']->id}", ['offer_lines' => [$line]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('offer_lines.0.commissions');

    expect($context['commission']->fresh())->not->toBeNull();
});
