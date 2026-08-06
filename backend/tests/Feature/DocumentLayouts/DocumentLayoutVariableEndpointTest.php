<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('documentLayoutUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentLayoutUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("document-layouts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-layouts.{$ability}");
        }

        return $user;
    }
}

it('requires authentication (401)', function () {
    $this->getJson('/api/document-layouts/variables?module=quotes')->assertUnauthorized();
});

it('403 without document-layouts.viewAny (AC-045)', function () {
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/variables?module=quotes')->assertForbidden();
});

it('422 without `module` (AC-045)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/variables')
        ->assertStatus(422)->assertJsonValidationErrors('module');
});

it('422 when `module` is out of the enum (AC-045)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/document-layouts/variables?module=invoices')
        ->assertStatus(422)->assertJsonValidationErrors('module');
});

it('200: the response envelope carries {module, categories} with the frozen category keys', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/document-layouts/variables?module=quotes')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.module', 'quotes')
        ->assertJsonStructure(['success', 'message', 'data' => ['module', 'categories' => [['key', 'label', 'variables']]]]);

    $categoryKeys = collect($response->json('data.categories'))->pluck('key')->all();
    expect($categoryKeys)->toBe([
        'quote', 'totals', 'client', 'opportunity', 'referent', 'commercial',
        'reporter', 'supervisor', 'company', 'company_site', 'operational_site',
        'custom_fields', 'quote_attributes', 'document',
    ]);

    $quoteCategory = collect($response->json('data.categories'))->firstWhere('key', 'quote');
    foreach ($quoteCategory['variables'] as $variable) {
        expect($variable['variable'])->not->toBeEmpty()
            ->and($variable['label'])->not->toBeEmpty()
            ->and($variable['type'])->not->toBeEmpty()
            ->and($variable['example'])->not->toBeEmpty();
    }

    // No variable duplicated across the whole catalogue.
    $allTokens = collect($response->json('data.categories'))
        ->flatMap(fn (array $category): array => collect($category['variables'])->pluck('variable')->all());
    expect($allTokens->count())->toBe($allTokens->unique()->count());
});

it('the catalogue never exposes a payment_method category or a discount token (D-3/D-4)', function () {
    $actor = documentLayoutUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/document-layouts/variables?module=quotes')->assertOk();

    $categoryKeys = collect($response->json('data.categories'))->pluck('key');
    expect($categoryKeys)->not->toContain('payment_method');

    $allTokens = collect($response->json('data.categories'))
        ->flatMap(fn (array $category): array => collect($category['variables'])->pluck('variable')->all());
    expect($allTokens->filter(fn (string $token): bool => str_contains($token, 'discount')))->toBeEmpty();
});
