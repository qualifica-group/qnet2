<?php

use App\Models\QuoteWorkflowStatus;
use Database\Seeders\DemoQuoteWorkflowSeeder;
use Database\Seeders\DemoSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The demo working statuses must carry their `description` (spec 0047
 * amendment): it is what the "(i)" marker next to every status badge shows,
 * so a demo database without descriptions leaves the feature invisible.
 */
it('seeds a description on every working status, system rows included', function (): void {
    test()->seed(DemoSourceSeeder::class);
    test()->seed(DemoQuoteWorkflowSeeder::class);

    $statuses = QuoteWorkflowStatus::query()->get();

    expect($statuses)->not->toBeEmpty();

    $statuses->each(function (QuoteWorkflowStatus $status): void {
        expect($status->description)->toBeString()->not->toBe('');
    });

    expect($statuses->where('system_key', 'open'))->not->toBeEmpty()
        ->and($statuses->whereNotNull('system_key')->pluck('description')->filter()->count())
        ->toBe($statuses->whereNotNull('system_key')->count());
});

it('marks the demo statuses that require an explanatory note', function (): void {
    test()->seed(DemoSourceSeeder::class);
    test()->seed(DemoQuoteWorkflowSeeder::class);

    expect(QuoteWorkflowStatus::query()->where('requires_note', true)->exists())->toBeTrue();
});

it('stays idempotent on a re-run', function (): void {
    test()->seed(DemoSourceSeeder::class);
    test()->seed(DemoQuoteWorkflowSeeder::class);

    $before = QuoteWorkflowStatus::query()->count();

    test()->seed(DemoQuoteWorkflowSeeder::class);

    expect(QuoteWorkflowStatus::query()->count())->toBe($before);
});
