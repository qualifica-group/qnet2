<?php

use App\Models\EmploymentProfile;
use App\Models\ExportRun;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Record visibility of Offerte (spec 0316): own (Supervisore / Gestore
 * account) -> own site (`quotes.viewSite`) -> all (`quotes.viewAll`). The
 * levels narrow, they never grant `quotes.view`.
 */
uses(RefreshDatabase::class);

function visibilityGridIds(): array
{
    return collect(test()->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))
        ->pluck('id')->sort()->values()->all();
}

function visibilityActorAtSite(array $abilities, OperationalSite $site): User
{
    $user = quoteScopedUserWith($abilities);
    EmploymentProfile::factory()->for($user)->physicalSite($site)->create();

    return $user;
}

it('AC-010: a view-only actor sees only quotes they supervise or manage', function () {
    $actor = quoteScopedUserWith(['viewAny', 'view']);
    $supervised = Quote::factory()->create(['supervisor_id' => $actor->id]);
    $managed = Quote::factory()->create();
    $managed->managers()->attach($actor->id, ['position' => 3]);
    Quote::factory()->create();
    Sanctum::actingAs($actor);

    expect(visibilityGridIds())->toBe(collect([$supervised->id, $managed->id])->sort()->values()->all());
});

it('AC-011: viewSite adds the quotes of the actor sites, never a quote without a site', function () {
    $site = OperationalSite::factory()->withAddress()->create();
    $actor = visibilityActorAtSite(['viewAny', 'view', 'viewSite'], $site);
    $atSite = Quote::factory()->create(['operational_site_id' => $site->id]);
    Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->withAddress()->create()->id]);
    Quote::factory()->create(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    expect(visibilityGridIds())->toBe([$atSite->id]);
});

it('AC-011: without viewSite the site of the actor grants nothing', function () {
    $site = OperationalSite::factory()->withAddress()->create();
    $actor = visibilityActorAtSite(['viewAny', 'view'], $site);
    Quote::factory()->create(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    expect(visibilityGridIds())->toBe([]);
});

it('AC-012: viewAll shows every quote, and the detail is 403 out of scope, 200 in scope', function () {
    $mine = Quote::factory()->create();
    $other = Quote::factory()->create();
    $scoped = quoteScopedUserWith(['viewAny', 'view']);
    $mine->managers()->attach($scoped->id, ['position' => 3]);

    Sanctum::actingAs($scoped);
    $this->getJson("/api/quotes/{$mine->id}")->assertOk();
    $this->getJson("/api/quotes/{$other->id}")->assertForbidden();

    Sanctum::actingAs(quoteScopedUserWith(['viewAny', 'view', 'viewAll']));
    expect(visibilityGridIds())->toBe(collect([$mine->id, $other->id])->sort()->values()->all());
    $this->getJson("/api/quotes/{$other->id}")->assertOk();
});

it('AC-012: viewAll without view sees nothing (levels narrow, never grant)', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteScopedUserWith(['viewAny', 'viewAll']));

    $this->getJson("/api/quotes/{$quote->id}")->assertForbidden();
});

it('AC-013: update and delete need their own permission AND visibility', function () {
    $quote = Quote::factory()->create();

    Sanctum::actingAs(quoteScopedUserWith(['viewAny', 'view', 'viewAll']));
    $this->putJson("/api/quotes/{$quote->id}", ['title' => 'X'])->assertForbidden();
    $this->deleteJson("/api/quotes/{$quote->id}")->assertForbidden();

    $outOfScope = quoteScopedUserWith(['viewAny', 'view', 'update', 'delete']);
    Sanctum::actingAs($outOfScope);
    $this->putJson("/api/quotes/{$quote->id}", ['title' => 'X'])->assertForbidden();
    $this->deleteJson("/api/quotes/{$quote->id}")->assertForbidden();
    expect(Quote::query()->whereKey($quote->id)->exists())->toBeTrue();
});

it('AC-014: quotes/for-select is scoped, also for an explicit ids[] hydration', function () {
    $actor = quoteScopedUserWith(['viewAny', 'view']);
    $mine = Quote::factory()->create(['supervisor_id' => $actor->id]);
    $other = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/quotes/for-select?ids[]={$other->id}")->assertOk();

    expect(array_column($response->json('items'), 'id'))->toBe([$mine->id]);
});

it('AC-014: /api/stats/quotes counts only visible quotes, per actor', function () {
    $first = quoteScopedUserWith(['viewAny', 'view']);
    $second = quoteScopedUserWith(['viewAny', 'view']);
    Quote::factory()->count(2)->create(['supervisor_id' => $first->id]);
    Quote::factory()->create(['supervisor_id' => $second->id]);
    Permission::findOrCreate('stats.view');

    $total = function (User $actor): int {
        Sanctum::actingAs($actor);

        return collect(test()->getJson('/api/stats/quotes')->assertOk()->json('data.widgets'))->firstWhere('key', 'total')['value'];
    };

    expect($total($first))->toBe(2)->and($total($second))->toBe(1);
});

it('AC-014: the grid export and filter values are scoped (same baseQuery)', function () {
    $actor = quoteScopedUserWith(['viewAny', 'view', 'export']);
    $mine = Quote::factory()->create(['supervisor_id' => $actor->id, 'title' => 'Mine']);
    Quote::factory()->create(['title' => 'Hidden']);
    Sanctum::actingAs($actor);

    $titles = $this->postJson('/api/tables/quotes/values', ['columnId' => 'title'])->json('data');

    expect(json_encode($titles))->toContain('Mine')->not->toContain('Hidden')
        ->and($mine->id)->toBeInt();
});

it('AC-042: a null actor matches no row and a super-admin sees everything', function () {
    Quote::factory()->count(2)->create();

    expect(QuoteVisibilityScope::scopeToActor(Quote::query(), null)->count())->toBe(0);

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin'));
    Sanctum::actingAs($admin);

    expect(visibilityGridIds())->toHaveCount(2);
});

it('isVisibleTo answers in memory when managers are loaded and agrees with the query shape', function () {
    $actor = quoteScopedUserWith(['view']);
    $quote = Quote::factory()->create();
    $quote->managers()->attach($actor->id, ['position' => 3]);

    expect(QuoteVisibilityScope::isVisibleTo($actor, $quote->load('managers')))->toBeTrue()
        ->and(QuoteVisibilityScope::isVisibleTo($actor, Quote::factory()->create()->load('managers')))->toBeFalse()
        ->and(QuoteVisibilityScope::isVisibleTo($actor, Quote::find($quote->id)))->toBeTrue();
});

it('AC-012: the quote document endpoint is 403 out of scope', function () {
    $actor = quoteScopedUserWith(['viewAny', 'view']);
    $other = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/quotes/{$other->id}/document", [])->assertForbidden();
});

it('AC-012: the activity log of a quote is 403 out of scope and 200 in scope', function () {
    $actor = quoteScopedUserWith(['viewAny', 'view', 'viewActivity']);
    $mine = Quote::factory()->create(['supervisor_id' => $actor->id]);
    $other = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/quotes/{$mine->id}")->assertOk();
    $this->getJson("/api/activity-log/quotes/{$other->id}")->assertForbidden();
});

it('AC-014: the grid export contains only the visible quotes', function () {
    Storage::fake('local');
    $actor = quoteScopedUserWith(['viewAny', 'view', 'export']);
    Quote::factory()->create(['supervisor_id' => $actor->id]);
    Quote::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/quotes', [
        'format' => 'csv',
        'columns' => [['colId' => 'code', 'header' => 'Code']],
    ])->assertCreated();

    expect(ExportRun::findOrFail($response->json('data.export_run.id'))->fresh()->row_count)->toBe(1);
});

it('AC-015: the opportunity panel lists only the visible quotes of that opportunity', function () {
    $actor = quoteScopedUserWith(['viewAny', 'view']);
    $opportunity = Opportunity::factory()->create();
    $mine = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $actor->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Quote::factory()->create(['supervisor_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'opportunityId' => $opportunity->id,
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
});
