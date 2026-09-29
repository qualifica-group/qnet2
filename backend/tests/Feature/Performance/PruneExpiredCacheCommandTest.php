<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedCacheRows(int $expired, int $valid): void
{
    $rows = [];
    for ($i = 0; $i < $expired; $i++) {
        $rows[] = ['key' => "old-{$i}", 'value' => 's', 'expiration' => time() - 100];
    }
    for ($i = 0; $i < $valid; $i++) {
        $rows[] = ['key' => "live-{$i}", 'value' => 's', 'expiration' => time() + 1000];
    }
    DB::table('cache')->insert($rows);
}

it('AC-024 deletes expired rows in chunks and keeps the valid ones', function () {
    config(['cache.default' => 'database', 'aggregate-cache.prune_chunk' => 10]);
    seedCacheRows(expired: 25, valid: 3);

    $this->artisan('cache:prune-expired')->expectsOutputToContain('Pruned 25')->assertSuccessful();

    expect(DB::table('cache')->where('expiration', '<', time())->count())->toBe(0)
        ->and(DB::table('cache')->count())->toBe(3);
});

it('AC-024 handles a number of expired rows that is an exact multiple of the chunk', function () {
    config(['cache.default' => 'database', 'aggregate-cache.prune_chunk' => 10]);
    seedCacheRows(expired: 20, valid: 1);

    $this->artisan('cache:prune-expired')->assertSuccessful();

    expect(DB::table('cache')->count())->toBe(1);
});

it('AC-024 is a no-op when the default store is not database', function () {
    config(['cache.default' => 'array']);
    seedCacheRows(expired: 5, valid: 0);

    $this->artisan('cache:prune-expired')->assertSuccessful();

    expect(DB::table('cache')->count())->toBe(5);
});

it('AC-024 is scheduled hourly without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'cache:prune-expired'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
