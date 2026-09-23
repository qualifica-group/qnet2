<?php

use App\Models\User;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Security/arch contract for EVERY registered table definition.
 *
 * These tests discover definitions from the registry (config/tables.php) at
 * runtime, so any domain added in the future is covered automatically —
 * nothing here is hardcoded to `users`. They guarantee the two fail-safe
 * invariants Security asked for: hidden model fields are never mapped into a
 * row, and viewAny is never trivially `true` (a permissionless actor is always
 * denied).
 */

/**
 * All registered definitions, resolved through the real registry (booted app).
 *
 * @return array<string, TableDefinition>
 */
function allRegisteredDefinitions(): array
{
    /** @var array<string, class-string<TableDefinition>> $map */
    $map = config('tables.definitions', []);
    $registry = app(TableRegistry::class);

    $definitions = [];
    foreach (array_keys($map) as $domain) {
        $definitions[$domain] = $registry->resolve($domain);
    }

    return $definitions;
}

it('registers at least one table definition to assert against', function () {
    expect(allRegisteredDefinitions())->not->toBeEmpty();
});

it('never maps any model hidden field into a row, for every definition', function () {
    foreach (allRegisteredDefinitions() as $domain => $definition) {
        /** @var class-string<Model> $modelClass */
        $modelClass = $definition->modelClass();

        // Build a real instance via the model factory so derived fields (e.g.
        // roles) and casts behave as in production.
        /** @var Model $model */
        $model = $modelClass::factory()->create();

        // Any authenticated actor is enough to exercise mapRow; reuse the model
        // when it is a User, otherwise spin up a throwaway user.
        $actor = $model instanceof User ? $model : User::factory()->create();

        $row = $definition->mapRow($actor, $model->fresh());

        $hidden = $model->getHidden();

        expect(array_intersect(array_keys($row), $hidden))
            ->toBe([], "[{$domain}] mapRow leaked a hidden field");

        // Belt-and-braces: the universal sensitive keys are never present.
        expect($row)->not->toHaveKey('password', "[{$domain}] mapRow exposed password")
            ->and($row)->not->toHaveKey('remember_token', "[{$domain}] mapRow exposed remember_token");
    }
});

/**
 * Domains where `viewAny` is DELIBERATELY true for every authenticated actor
 * (spec-approved, not an oversight): `notifications` (spec 0150, D-1) has no
 * Spatie permission of its own by design — every user browses their OWN
 * notifications, and the real fail-closed boundary is row-level, enforced
 * unconditionally by NotificationsTableDefinition::baseQuery() (scoped to the
 * actor's own notifiable; a null actor sees zero rows), not this gate.
 *
 * @var array<int, string>
 */
const PERMISSIONLESS_VIEW_ANY_DOMAINS = ['notifications'];

it('denies viewAny for a permissionless actor, for every definition (not trivially true)', function () {
    foreach (allRegisteredDefinitions() as $domain => $definition) {
        if (in_array($domain, PERMISSIONLESS_VIEW_ANY_DOMAINS, true)) {
            continue;
        }

        // Fresh user: no roles, no permissions. A fail-open definition that
        // returned a hardcoded true would fail this assertion.
        $actor = User::factory()->create();

        expect($definition->authorizeViewAny($actor))
            ->toBeFalse("[{$domain}] authorizeViewAny is fail-open for a permissionless actor");
    }
});

it('scopes every row of a deliberately permissionless-viewAny domain to the actor, fail-closed (notifications, D-1)', function () {
    foreach (PERMISSIONLESS_VIEW_ANY_DOMAINS as $domain) {
        $definition = allRegisteredDefinitions()[$domain];

        expect($definition->authorizeViewAny(User::factory()->create()))->toBeTrue();

        // No authenticated actor at all (e.g. a queued export run without one):
        // baseQuery() must resolve to a query that can never match a row.
        expect($definition->baseQuery()->count())->toBe(0);
    }
});
