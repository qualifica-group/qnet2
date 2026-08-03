<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * User directive 2026-08-03: "e' un errore in inglese, bisogna tradurre".
 *
 * Every user-facing message the API returns is rendered in the REQUEST locale,
 * which App\Http\Middleware\SetLocale resolves from `Accept-Language` — the
 * header the frontend now sets to its OWN active language. Without it the app
 * stayed on APP_LOCALE (en) for every call except the public bootstrap one, so
 * the Italian catalogue shipped in `lang/it*` was unreachable and errors
 * surfaced in English inside an Italian UI.
 */
uses(RefreshDatabase::class);

if (! function_exists('localeActor')) {
    function localeActor(): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.viewAny', 'request-management.update']);

        return $user;
    }
}

if (! function_exists('localeCategory')) {
    function localeCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

if (! function_exists('localeRequest')) {
    function localeRequest(User $manager, ProductCategory $category): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

if (! function_exists('localePatch')) {
    /** @param  array<int, array<string, mixed>>  $value */
    function localePatch(Opportunity $opportunity, array $value, ?string $language = 'it')
    {
        $request = $language === null ? test() : test()->withHeader('Accept-Language', $language);

        return $request->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
            'column' => 'product_categories',
            'value' => $value,
        ]);
    }
}

it('answers the coherence refusal in Italian when the client asks for it', function () {
    $actor = localeActor();
    $category = localeCategory();
    $opportunity = localeRequest($actor, $category);
    $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fibra 1000']);
    $opportunity->productsOfInterest()->sync([$product->id]);
    $replacement = localeCategory();
    Sanctum::actingAs($actor);

    $response = localePatch($opportunity, [[
        'business_function_id' => (int) $replacement->business_function_id,
        'product_category_id' => $replacement->id,
    ]], 'it-IT,it;q=0.9')->assertStatus(422);

    expect($response->json('message'))
        ->toContain('Questi prodotti di interesse appartengono a una categoria prodotto che la richiesta non ha')
        ->toContain('Fibra 1000');
});

it('translates the product-line rules and names their fields readably', function () {
    $actor = localeActor();
    $category = localeCategory();
    $opportunity = localeRequest($actor, $category);
    $other = localeCategory();
    Sanctum::actingAs($actor);

    // The category belongs to its OWN business function, not to this one.
    expect(localePatch($opportunity, [[
        'business_function_id' => (int) $category->business_function_id,
        'product_category_id' => $other->id,
    ]])->assertStatus(422)->json('message'))
        ->toBe('Questa categoria prodotto non appartiene alla funzione aziendale selezionata.');

    // A Laravel rule message, with the attribute named for a human instead of
    // "product lines.0.business function id".
    expect(localePatch($opportunity, [[
        'business_function_id' => 0,
        'product_category_id' => $other->id,
    ]])->assertStatus(422)->json('message'))->toContain('funzione aziendale');
});

it('translates the generic inline-edit engine messages too', function () {
    $actor = localeActor();
    $opportunity = localeRequest($actor, localeCategory());
    Sanctum::actingAs($actor);

    localePatch($opportunity, [])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Questo campo è obbligatorio.');

    test()->withHeader('Accept-Language', 'it')
        ->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
            'column' => 'general_notes',
            'value' => 'x',
        ])->assertStatus(422)
        ->assertJsonPath('message', 'La colonna [general_notes] non è modificabile.');
});

it('keeps English for a client that does not ask for Italian', function () {
    $actor = localeActor();
    $opportunity = localeRequest($actor, localeCategory());
    Sanctum::actingAs($actor);

    localePatch($opportunity, [], language: null)
        ->assertStatus(422)
        ->assertJsonPath('message', 'This field is required.');
});

it('translates the shared envelope messages (403 and 404)', function () {
    $actor = localeActor();
    $opportunity = localeRequest($actor, localeCategory());
    Sanctum::actingAs($actor);

    // A row outside the actor's own scope is a 404 with the generic envelope.
    $foreign = Opportunity::factory()->create();
    test()->withHeader('Accept-Language', 'it')
        ->patchJson("/api/tables/request-management/rows/{$foreign->id}", [
            'column' => 'product_categories',
            'value' => [],
        ])->assertStatus(404)
        ->assertJsonPath('message', 'Risorsa non trovata.');

    // Without the update ability the same cell is a 403.
    $reader = User::factory()->create();
    $reader->givePermissionTo('request-management.viewAny');
    $opportunity->managers()->sync([$reader->id => ['position' => 2]]);
    Sanctum::actingAs($reader);

    localePatch($opportunity, [[
        'business_function_id' => 1,
        'product_category_id' => 1,
    ]])->assertStatus(403)
        ->assertJsonPath('message', 'Non hai i permessi per questa azione.');
});
