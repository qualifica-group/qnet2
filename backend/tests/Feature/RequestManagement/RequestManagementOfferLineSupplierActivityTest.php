<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0320 D-5: a change of the offer line's supplier alone, made from
 * Gestione Richieste, is a real change and lands in the opportunity log.
 */
uses(RefreshDatabase::class);

it('a supplier-only change of an offer line is written to the opportunity activity log', function () {
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.viewAny', 'request-management.view', 'request-management.update', 'request-management.viewAll']);

    $product = productWithSupplier(['category_id' => ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id])->id]);
    $first = (int) defaultSupplierId($product);
    $second = Registry::factory()->supplier()->create();
    ProductSupplier::factory()->create(['product_id' => $product->id, 'supplier_id' => $second->id]);

    $quote = Quote::factory()->for(Opportunity::factory())->create();
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'supplier_id' => $first,
        'quantity' => 1,
        'unit_price' => 100,
        'net_amount' => 100,
        'total_amount' => 100,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [[
            'id' => $line->id,
            'product_id' => $product->id,
            'supplier_id' => $second->id,
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate_id' => $line->vat_rate_id,
        ]],
    ])->assertOk();

    expect($line->fresh()->supplier_id)->toBe($second->id);

    $activity = Activity::query()
        ->where('subject_type', $quote->opportunity->getMorphClass())
        ->where('subject_id', $quote->opportunity_id)
        ->where('event', 'updated')
        ->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('attributes'))->toHaveKey('offer_lines');
});
