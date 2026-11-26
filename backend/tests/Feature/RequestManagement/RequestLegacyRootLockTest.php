<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0432: an unbound request with product lines of the old catalogue
// (`is_legacy`) cannot gain a line of another root ("categoria madre"); a
// request of the new catalogue stays free.

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        $user->givePermissionTo(Permission::findOrCreate("request-management.{$ability}"));
    }
    Sanctum::actingAs($user);

    $function = BusinessFunction::factory()->create();
    $this->category = static fn (string $name, ?ProductCategory $parent = null, bool $legacy = false): ProductCategory => ($parent === null
        ? ProductCategory::factory()
        : ProductCategory::factory()->childOf($parent))->create(['name' => $name, 'business_function_id' => $function->id, 'is_legacy' => $legacy]);

    $this->oldGare = ($this->category)('[OLD] GARE-', legacy: true);
    $this->oldGareChild = ($this->category)('[OLD] GARE', $this->oldGare, legacy: true);
    $this->oldGareOther = ($this->category)('[OLD] GARE bis', $this->oldGare, legacy: true);
    $this->iso = ($this->category)('ISO');
    $this->iso9001 = ($this->category)('ISO 9001', $this->iso);
    $this->sicurezza = ($this->category)('Sicurezza');
    $this->corsi = ($this->category)('Sicurezza - Corsi', $this->sicurezza);

    // An unbound request (spec 0411: root_product_category_id null) on $categories.
    $this->request = static function (array $categories): Quote {
        $opportunity = Opportunity::factory()->create();
        foreach ($categories as $category) {
            OpportunityProductLine::factory()->create(['opportunity_id' => $opportunity->id, 'product_category_id' => $category->id, 'business_function_id' => $category->business_function_id]);
        }

        return Quote::factory()->for($opportunity)->create(['root_product_category_id' => null]);
    };
});

/**
 * @param  array<int, ProductCategory>  $categories
 */
function legacyLockPatch(Quote $quote, array $categories): TestResponse
{
    return test()->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => array_map(static fn (ProductCategory $category): array => ['product_category_id' => $category->id], $categories),
    ]);
}

function legacyLockLineIds(Quote $quote): array
{
    return OpportunityProductLine::query()->where('opportunity_id', $quote->opportunity_id)
        ->pluck('product_category_id')->sort()->values()->all();
}

it('AC-001: a legacy request cannot add a line of another root', function () {
    $quote = ($this->request)([$this->oldGareChild]);

    legacyLockPatch($quote, [$this->oldGareChild, $this->iso9001])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_lines']);

    expect(legacyLockLineIds($quote))->toBe([$this->oldGareChild->id]);
});

it('AC-002: a legacy request cannot swap its old root for a new one', function () {
    $quote = ($this->request)([$this->oldGareChild]);

    legacyLockPatch($quote, [$this->iso9001])->assertUnprocessable()->assertJsonValidationErrors(['product_lines']);
});

it('AC-003: a legacy request may add a category under a root it already has', function () {
    $quote = ($this->request)([$this->oldGareChild]);

    legacyLockPatch($quote, [$this->oldGareChild, $this->oldGareOther])->assertOk();

    expect(legacyLockLineIds($quote))->toBe(collect([$this->oldGareChild->id, $this->oldGareOther->id])->sort()->values()->all());
});

it('AC-004: a mixed legacy request may drop a root and keep the others, but not add a third', function () {
    $quote = ($this->request)([$this->oldGareChild, $this->iso9001]);

    legacyLockPatch($quote, [$this->oldGareChild, $this->iso9001, $this->corsi])->assertUnprocessable();

    legacyLockPatch($quote, [$this->iso9001])->assertOk();
    expect(legacyLockLineIds($quote))->toBe([$this->iso9001->id]);
});

it('AC-005: a request of the new catalogue may still add another root', function () {
    $quote = ($this->request)([$this->iso9001]);

    legacyLockPatch($quote, [$this->iso9001, $this->corsi])->assertOk();

    expect(legacyLockLineIds($quote))->toBe(collect([$this->iso9001->id, $this->corsi->id])->sort()->values()->all());
});

it('AC-006: the work panel lists the roots a legacy request is locked to, none otherwise', function () {
    $legacy = ($this->request)([$this->oldGareChild, $this->iso9001]);
    $current = ($this->request)([$this->iso9001]);

    expect($this->getJson("/api/request-management/{$legacy->id}")->assertOk()->json('data.legacy_root_categories'))
        ->toBe([['id' => $this->iso->id, 'name' => 'ISO'], ['id' => $this->oldGare->id, 'name' => '[OLD] GARE-']])
        ->and($this->getJson("/api/request-management/{$current->id}")->assertOk()->json('data.legacy_root_categories'))
        ->toBe([]);
});
