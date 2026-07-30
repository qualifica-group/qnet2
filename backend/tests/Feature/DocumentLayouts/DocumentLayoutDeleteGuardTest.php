<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * DELETE /api/document-layouts/{documentLayout} — the usage guard (spec
 * 0070, D-7): a layout referenced by at least one Quote cannot be deleted,
 * evaluated AFTER the pre-existing default guard (spec 0069, frozen order).
 */
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

it('AC-280: DELETE of a layout referenced by 3 quotes is 422 layout_in_use, count in the message, record survives and stays deactivatable', function () {
    $actor = documentLayoutUserWith(['delete', 'update']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false, 'is_active' => true]);
    Quote::factory()->count(3)->create(['layout_id' => $layout->id]);
    Sanctum::actingAs($actor);

    $response = $this->deleteJson("/api/document-layouts/{$layout->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('quotes');

    expect($response->json('errors.quotes.0'))->toBe(__('document_layouts.layout_in_use', ['count' => 3]))
        ->and($response->json('errors.quotes.0'))->toContain('3');

    $this->assertDatabaseHas('document_layouts', ['id' => $layout->id]);

    $this->patchJson("/api/document-layouts/{$layout->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('AC-281: a layout that is BOTH default and in use fails on the earlier default guard (frozen order)', function () {
    $actor = documentLayoutUserWith(['delete']);
    $default = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true, 'is_active' => true]);
    DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false]);
    Quote::factory()->create(['layout_id' => $default->id]);
    Sanctum::actingAs($actor);

    $response = $this->deleteJson("/api/document-layouts/{$default->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('is_default');

    expect($response->json('errors.is_default.0'))->toBe(__('document_layouts.default_cannot_be_deleted'))
        ->and($response->json('errors'))->not->toHaveKey('quotes');

    $this->assertDatabaseHas('document_layouts', ['id' => $default->id]);
});

it('AC-282: DELETE of an unused, non-default layout succeeds with 204', function () {
    $actor = documentLayoutUserWith(['delete']);
    DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => true]);
    $target = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/document-layouts/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('document_layouts', ['id' => $target->id]);
});

it('AC-283: the usage count is read via a single count() query, never hydrating quote rows', function () {
    $actor = documentLayoutUserWith(['delete']);
    $layout = DocumentLayout::factory()->create(['module' => 'quotes', 'is_default' => false]);
    Quote::factory()->count(2)->create(['layout_id' => $layout->id]);
    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->deleteJson("/api/document-layouts/{$layout->id}")->assertStatus(422);
    $queries = DB::getQueryLog();
    DB::flushQueryLog();

    $quoteQueries = array_values(array_filter(
        $queries,
        static fn (array $entry): bool => str_contains(strtolower((string) $entry['query']), 'from "quotes"'),
    ));

    expect($quoteQueries)->not->toBeEmpty();

    foreach ($quoteQueries as $entry) {
        expect(strtolower((string) $entry['query']))->toContain('count(');
    }
});
