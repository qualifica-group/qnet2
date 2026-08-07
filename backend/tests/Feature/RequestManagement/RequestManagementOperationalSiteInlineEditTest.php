<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/tables/request-management/columns + PATCH .../rows/{row} — the
// operational site as an inline-editable relation column, and the row-scoped
// narrowing it gives the operator picker next to it (user directive
// 2026-07-23): in the grid, as in the "Lavora" panel, the operators offered
// are those of the chosen site.

uses(RefreshDatabase::class);

if (! function_exists('siteInlineEditActor')) {
    /**
     * @param  array<int, string>  $abilities  request-management abilities
     */
    function siteInlineEditActor(array $abilities, bool $canViewSites = true): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('operational-sites.viewAny');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        if ($canViewSites) {
            $user->givePermissionTo('operational-sites.viewAny');
        }

        return $user;
    }
}

if (! function_exists('siteInlineEditRequest')) {
    /** A quote the actor supervises (the module's own row scope, D-3). */
    function siteInlineEditRequest(User $supervisor): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$supervisor->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);
    }
}

if (! function_exists('siteInlineEditColumns')) {
    /** @return Collection<string, array<string, mixed>> */
    function siteInlineEditColumns(): Collection
    {
        return collect(test()->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
            ->keyBy('id');
    }
}

// ---------------------------------------------------------------------------
// The config the grid builds its two linked editors from
// ---------------------------------------------------------------------------

it('operational_site advertises a relation editor over operational-sites', function () {
    Sanctum::actingAs(siteInlineEditActor(['viewAny', 'update']));

    $column = siteInlineEditColumns()['operational_site'];

    expect($column['editable'])->toBeTrue()
        ->and($column['editor'])->toBe('relation')
        ->and($column['relation']['resource'])->toBe('operational-sites');
});

it('operator_ga2 declares the row-scoped param that narrows its picker to the row site', function () {
    Sanctum::actingAs(siteInlineEditActor(['viewAny', 'update']));

    $column = siteInlineEditColumns()['operator_ga2'];

    expect($column['relation']['resource'])->toBe('users')
        ->and($column['relation']['scope'])->toBe(['operational_site_id' => 'operational_site']);
});

it('a column without a declared scope emits no scope key at all', function () {
    Sanctum::actingAs(siteInlineEditActor(['viewAny', 'update']));

    expect(siteInlineEditColumns()['operational_site']['relation'])->not->toHaveKey('scope');
});

it('without operational-sites.viewAny the site column stays read-only', function () {
    Sanctum::actingAs(siteInlineEditActor(['viewAny', 'update'], canViewSites: false));

    expect(siteInlineEditColumns()['operational_site']['editable'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// The write path
// ---------------------------------------------------------------------------

it('PATCH operational_site persists the FK and returns the row with the composed label', function () {
    $actor = siteInlineEditActor(['viewAny', 'update']);
    $quote = siteInlineEditRequest($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $row = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operational_site',
        'value' => $site->id,
    ])->assertOk()->json('data');

    expect($quote->fresh()->operational_site_id)->toBe($site->id)
        ->and($row['operational_site']['id'])->toBe($site->id)
        ->and($row['operational_site']['label'])->toBeString()->not->toBeEmpty();
});

it('PATCH operational_site writes ONLY quotes.operational_site_id — opportunities.operational_site_id stays untouched (AC-018)', function () {
    $actor = siteInlineEditActor(['viewAny', 'update']);
    $quote = siteInlineEditRequest($actor);
    $opportunitySite = OperationalSite::factory()->withAddress()->create();
    $quote->opportunity->update(['operational_site_id' => $opportunitySite->id]);
    $newSite = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operational_site',
        'value' => $newSite->id,
    ])->assertOk();

    expect($quote->fresh()->operational_site_id)->toBe($newSite->id)
        ->and($quote->opportunity->fresh()->operational_site_id)->toBe($opportunitySite->id);
});

it('PATCH operational_site with null clears the site', function () {
    $actor = siteInlineEditActor(['viewAny', 'update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $quote = siteInlineEditRequest($actor);
    $quote->update(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operational_site',
        'value' => null,
    ])->assertOk();

    expect($quote->fresh()->operational_site_id)->toBeNull();
});

it('PATCH operational_site with an unknown id -> 422, nothing written', function () {
    $actor = siteInlineEditActor(['viewAny', 'update']);
    $quote = siteInlineEditRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operational_site',
        'value' => 999999,
    ])->assertStatus(422);

    expect($quote->fresh()->operational_site_id)->toBeNull();
});

// 403, not 422: without `operational-sites.viewAny` the field-permission
// ceiling (RequestManagementAuthorization) resolves the field READ-ONLY, and
// that check (step 4) runs before value validation — the engine keeps the two
// failure reasons on distinct status codes by design.
it('PATCH operational_site without operational-sites.viewAny -> 403, nothing written', function () {
    $actor = siteInlineEditActor(['viewAny', 'update'], canViewSites: false);
    $quote = siteInlineEditRequest($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operational_site',
        'value' => $site->id,
    ])->assertForbidden();

    expect($quote->fresh()->operational_site_id)->toBeNull();
});
