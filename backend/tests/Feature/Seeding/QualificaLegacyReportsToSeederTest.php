<?php

use App\Models\User;
use Database\Seeders\QualificaLegacyReportsToSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// One-shot (user directive 2026-11-12): "Risponde a" aligned to the legacy
// `user_responses`, read live from the legacy `users` migration endpoint.
uses(RefreshDatabase::class);

/**
 * @param  array<int, list<int>>  $legacyManagers  legacy user id => legacy manager ids
 */
function fakeLegacyReportsTo(array $legacyManagers): void
{
    $items = collect($legacyManagers)
        ->map(fn (array $managerIds, int $id): array => ['id' => $id, 'reports_to_ids' => $managerIds])
        ->values();

    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => fn (Request $request) => Http::response([
            'items' => $items->slice((int) $request['offset'], (int) $request['limit'])->values()->all(),
            'pagination' => ['total' => $items->count()],
        ]),
    ]);
}

function migratedUser(int $oldId): User
{
    return User::factory()->create(['old_id' => $oldId]);
}

/**
 * @param  list<User>  $managers
 */
function reportingTo(User $user, array $managers): void
{
    ($user->employment ?? $user->employment()->create())->reportsTo()->sync(collect($managers)->pluck('id')->all());
}

/**
 * @return list<int>
 */
function managerIdsOf(User $user): array
{
    return $user->fresh()->employment?->reportsTo()->pluck('users.id')->sort()->values()->all() ?? [];
}

beforeEach(fn () => seedMigrationsConfig());

it('replaces the seeded managers with the legacy ones, several managers included', function (): void {
    $nicoletta = migratedUser(323);
    $ilaria = migratedUser(242);
    $jessica = migratedUser(102);
    $caterina = migratedUser(500);
    $michela = migratedUser(501);
    $martina = migratedUser(502);
    reportingTo($nicoletta, [$jessica]);
    reportingTo($caterina, [$michela]);

    fakeLegacyReportsTo([323 => [242], 500 => [501, 502], 242 => [], 102 => [], 501 => [], 502 => []]);

    test()->seed(QualificaLegacyReportsToSeeder::class);

    expect(managerIdsOf($nicoletta))->toBe([$ilaria->id])
        ->and(managerIdsOf($caterina))->toBe(collect([$michela->id, $martina->id])->sort()->values()->all());
});

it('clears the managers the legacy does not have and creates the profile the link needs', function (): void {
    $boss = migratedUser(10);
    $withoutManagers = migratedUser(11);
    $withoutProfile = migratedUser(12);
    reportingTo($withoutManagers, [$boss]);

    fakeLegacyReportsTo([10 => [], 11 => [], 12 => [10]]);

    test()->seed(QualificaLegacyReportsToSeeder::class);

    expect(managerIdsOf($withoutManagers))->toBe([])
        ->and(managerIdsOf($withoutProfile))->toBe([$boss->id])
        ->and($boss->fresh()->employment)->toBeNull();
});

it('leaves alone users the legacy does not list and skips managers not migrated', function (): void {
    $boss = migratedUser(10);
    $qnetOnly = User::factory()->create();
    $notListed = migratedUser(20);
    $listed = migratedUser(21);
    reportingTo($qnetOnly, [$boss]);
    reportingTo($notListed, [$boss]);

    fakeLegacyReportsTo([10 => [], 21 => [10, 999]]);

    test()->seed(QualificaLegacyReportsToSeeder::class);

    expect(managerIdsOf($qnetOnly))->toBe([$boss->id])
        ->and(managerIdsOf($notListed))->toBe([$boss->id])
        ->and(managerIdsOf($listed))->toBe([$boss->id]);
});

it('reads every page of the legacy listing and is idempotent', function (): void {
    $boss = migratedUser(1);
    $last = migratedUser(150);
    $legacy = array_fill_keys(range(1, 149), []);
    $legacy[150] = [1];

    fakeLegacyReportsTo($legacy);

    test()->seed(QualificaLegacyReportsToSeeder::class);
    test()->seed(QualificaLegacyReportsToSeeder::class);

    expect(managerIdsOf($last))->toBe([$boss->id]);
    Http::assertSent(fn (Request $request): bool => (int) $request['offset'] === 100);
});
