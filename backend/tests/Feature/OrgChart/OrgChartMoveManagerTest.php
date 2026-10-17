<?php

use App\Authorization\FieldPermission;
use App\Authorization\FieldPermissionRepository;
use App\Authorization\UsersAuthorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function managerMovePayload(?User $from, ?User $to): array
{
    return ['from_manager_id' => $from?->id, 'to_manager_id' => $to?->id];
}

it('0273 AC-003: replaces the source manager with the destination, keeps the others and logs the move', function () {
    $a = orgChartPerson('A');
    $c = orgChartPerson('C');
    $keep = orgChartPerson('Keep');
    $b = orgChartPerson('B', [], $a, $keep);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $response = $this->postJson("/api/org-chart/people/{$b->id}/manager", managerMovePayload($a, $c))->assertOk();

    expect($response->json('data'))->toMatchArray(['id' => $b->id, 'name' => 'B', 'is_manager' => false])
        ->and($response->json('data.manager_ids'))->toEqualCanonicalizing([$keep->id, $c->id])
        ->and($b->employment->reportsTo()->pluck('users.id')->all())->toEqualCanonicalizing([$keep->id, $c->id]);

    $log = Activity::query()->where('log_name', 'org-chart')->sole();
    expect($log->event)->toBe('moved')
        ->and($log->subject_id)->toBe($b->id)
        ->and($log->properties->toArray())->toBe([
            'kind' => 'manager',
            'from' => ['id' => $a->id, 'name' => 'A'],
            'to' => ['id' => $c->id, 'name' => 'C'],
        ]);
});

it('0273 AC-003: a root becomes a child (from null) and a child becomes a root (to null)', function () {
    $a = orgChartPerson('A');
    $root = orgChartPerson('Root');
    $child = orgChartPerson('Child', [], $a);
    Sanctum::actingAs(orgChartActor(['users.update']));

    $this->postJson("/api/org-chart/people/{$root->id}/manager", managerMovePayload(null, $a))->assertOk();
    $this->postJson("/api/org-chart/people/{$child->id}/manager", managerMovePayload($a, null))->assertOk()
        ->assertJsonPath('data.manager_ids', []);

    expect($root->employment->reportsTo()->pluck('users.id')->all())->toBe([$a->id])
        ->and(Activity::query()->where('log_name', 'org-chart')->count())->toBe(2);
});

it('0273 AC-003: the move shows up in the existing person history', function () {
    $a = orgChartPerson('A');
    $b = orgChartPerson('B', [], $a);
    $actor = orgChartActor(['users.update', 'users.view', 'users.viewActivity']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/org-chart/people/{$b->id}/manager", managerMovePayload($a, null))->assertOk();

    $moved = collect($this->getJson("/api/activity-log/users/{$b->id}")->assertOk()->json('data.items'))->firstWhere('event', 'moved');
    expect($moved['causer']['id'])->toBe($actor->id)
        ->and($moved['changes'])->toBe([[
            'field' => 'manager', 'old_value' => $a->id, 'new_value' => null,
            'old_display' => 'A', 'new_display' => null,
        ]]);
});

it('0273 AC-004: refuses a cycle, a manager under someone, a stale source and an inactive target', function (string $case) {
    $top = orgChartPerson('Top', ['is_manager' => false]);
    $mid = orgChartPerson('Mid', [], $top);
    $low = orgChartPerson('Low', [], $mid);
    $boss = orgChartPerson('Boss', ['is_manager' => true]);
    $gone = orgChartPerson('Gone');
    $gone->forceFill(['is_active' => false])->save();
    Sanctum::actingAs(orgChartActor(['users.update']));

    [$subject, $from, $to] = match ($case) {
        'under a subordinate' => [$top, null, $low],
        'under itself' => [$mid, $top, $mid],
        'manager under someone' => [$boss, null, $top],
        'from not a manager' => [$low, $top, $boss],
        'inactive target' => [$low, $mid, $gone],
    };

    $this->postJson("/api/org-chart/people/{$subject->id}/manager", managerMovePayload($from, $to))
        ->assertUnprocessable();

    expect(Activity::query()->where('log_name', 'org-chart')->count())->toBe(0)
        ->and($low->employment->reportsTo()->pluck('users.id')->all())->toBe([$mid->id])
        ->and($top->employment->reportsTo()->count())->toBe(0);
})->with(['under a subordinate', 'under itself', 'manager under someone', 'from not a manager', 'inactive target']);

it('0273 AC-007: refuses the manager move without users.update or with a locked field', function () {
    $a = orgChartPerson('A');
    $b = orgChartPerson('B', [], $a);
    Sanctum::actingAs(orgChartActor(['org-chart.view', 'users.view']));

    $this->postJson("/api/org-chart/people/{$b->id}/manager", managerMovePayload($a, null))->assertForbidden();

    expect($b->employment->reportsTo()->count())->toBe(1);
});

it('0273 AC-007: refuses the manager move when the field is locked for the actor', function () {
    $a = orgChartPerson('A');
    $b = orgChartPerson('B', [], $a);
    $actor = orgChartActor(['users.update']);
    Sanctum::actingAs($actor);
    app()->bind(UsersAuthorization::class, fn () => new class(app(FieldPermissionRepository::class)) extends UsersAuthorization
    {
        protected function fieldPermissionCeiling(User $actor, ?Model $model): array
        {
            return ['employment.reports_to_ids' => FieldPermission::visibleReadonly()];
        }
    });

    $this->postJson("/api/org-chart/people/{$b->id}/manager", managerMovePayload($a, null))->assertForbidden();

    expect($b->employment->reportsTo()->count())->toBe(1);
});

it('0273 AC-004: validates the payload and 404s a missing person', function () {
    Sanctum::actingAs(orgChartActor(['users.update']));
    $b = orgChartPerson('B');

    $this->postJson("/api/org-chart/people/{$b->id}/manager", [])->assertUnprocessable();
    $this->postJson('/api/org-chart/people/999999/manager', managerMovePayload(null, null))->assertNotFound();
});
