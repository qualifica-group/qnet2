<?php

use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0171 rev.2: the Offerta title follows the opportunity flow. Automatic
 * = `<code> - <revenue products>` (code alone without revenue lines), kept in
 * sync with the offer's own lines; a typed title is manual and never
 * overwritten; null goes back to automatic.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteTitleLine')) {
    /** @return array{product_id: int, quantity: int, unit_price: int} */
    function quoteTitleLine(string $productName): array
    {
        return ['product_id' => nameDerivationRevenueProduct($productName)->id, 'quantity' => 1, 'unit_price' => 10];
    }
}

it('AC-010: an offer without revenue lines gets the code alone, automatic', function () {
    // The API requires a line; a system path (lead conversion with no
    // product) is the one that creates an offer without any.
    $quote = nameDerivationQuoteService()->create(
        nameDerivationCreateQuoteData(Opportunity::factory()->create()->id),
        nameDerivationActor(),
    );

    expect($quote->title)->toBe($quote->code)
        ->and($quote->title_is_manual)->toBeFalse();
});

it('AC-010: POST without title derives "<code> - <products>" from its own revenue lines', function () {
    Sanctum::actingAs(quoteTableUserWith(['create']));

    $response = $this->postJson('/api/quotes', [
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [quoteTitleLine('ISO 9001'), quoteTitleLine('SOA')],
    ])->assertCreated();

    expect($response->json('data.title'))->toBe($response->json('data.code').' - ISO 9001 + SOA');
});

it('AC-010: POST with a typed title keeps it as manual', function () {
    Sanctum::actingAs(quoteTableUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Offerta certificazioni',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [quoteTitleLine('ISO 9001')],
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Offerta certificazioni')
        ->assertJsonPath('data.title_is_manual', true);
});

it('AC-011: changing the lines re-derives an automatic title, never a manual one', function () {
    Sanctum::actingAs(quoteTableUserWith(['create', 'update']));
    $opportunityId = Opportunity::factory()->create()->id;
    $automatic = Quote::findOrFail($this->postJson('/api/quotes', [
        'opportunity_id' => $opportunityId,
        'offer_lines' => [quoteTitleLine('ISO 9001')],
    ])->assertCreated()->json('data.id'));
    $manual = Quote::findOrFail($this->postJson('/api/quotes', [
        'title' => 'Mio titolo',
        'opportunity_id' => $opportunityId,
        'offer_lines' => [quoteTitleLine('ISO 9001')],
    ])->assertCreated()->json('data.id'));

    $this->patchJson("/api/quotes/{$automatic->id}", ['offer_lines' => [quoteTitleLine('SOA')]])
        ->assertOk()
        ->assertJsonPath('data.title', $automatic->code.' - SOA');
    $this->patchJson("/api/quotes/{$manual->id}", ['offer_lines' => [quoteTitleLine('HACCP')]])
        ->assertOk()
        ->assertJsonPath('data.title', 'Mio titolo');
});

it('AC-011: PATCH title null goes back to the automatic title', function () {
    Sanctum::actingAs(quoteTableUserWith(['create', 'update']));
    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Mio titolo',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [quoteTitleLine('ISO 9001')],
    ])->json('data.id');
    $code = Quote::findOrFail($quoteId)->code;

    $this->patchJson("/api/quotes/{$quoteId}", ['title' => null])
        ->assertOk()
        ->assertJsonPath('data.title', $code.' - ISO 9001')
        ->assertJsonPath('data.title_is_manual', false);
});

it('AC-012: the migration marks OPP_<n> copies automatic and every other title manual', function () {
    $migration = 'database/migrations/2026_09_28_130000_add_title_is_manual_to_quotes_table.php';
    $systemCopy = Quote::factory()->create(['title' => 'OPP_12']);
    $typed = Quote::factory()->create(['title' => 'Offerta scritta a mano']);

    Artisan::call('migrate:rollback', ['--path' => $migration]);
    Artisan::call('migrate', ['--path' => $migration]);

    expect((bool) Quote::findOrFail($systemCopy->id)->title_is_manual)->toBeFalse()
        ->and((bool) Quote::findOrFail($typed->id)->title_is_manual)->toBeTrue();
});

it('AC-013: titles:recalculate re-derives only the automatic titles', function () {
    $opportunity = Opportunity::factory()->create(['name' => 'ISO 9001']);
    $automatic = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'title' => 'OPP_1']);
    $manual = Quote::factory()->create(['title' => 'Mio titolo', 'title_is_manual' => true]);

    Artisan::call('titles:recalculate');

    expect($opportunity->fresh()->name)->toBe('OPP_'.$opportunity->id)
        ->and($automatic->fresh()->title)->toBe($automatic->code)
        ->and($manual->fresh()->title)->toBe('Mio titolo');
});
