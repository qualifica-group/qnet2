<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Source;
use Database\Seeders\SourcesDeduplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The duplicate Fonti are merged into the one kept: their records follow and
 * the duplicates are deleted.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (SourcesDeduplicationSeeder::MERGES as $pair) {
        foreach ($pair as [$id, $name]) {
            Source::query()->whereKey($id)->exists() || Source::query()->forceCreate(['id' => $id, 'name' => $name]);
        }
    }
});

it('moves every record of a duplicate to the kept source and deletes the duplicate', function (): void {
    $registry = Registry::factory()->create(['source_id' => 9]);
    $lead = Lead::factory()->create(['source_id' => 11]);
    $campaign = Campaign::factory()->create(['source_id' => 14]);
    $quote = Quote::factory()->create(['source_id' => 15]);
    $untouched = Registry::factory()->create(['source_id' => 2]);

    $this->seed(SourcesDeduplicationSeeder::class);

    expect($registry->fresh()->source_id)->toBe(12)
        ->and($lead->fresh()->source_id)->toBe(1)
        ->and($campaign->fresh()->source_id)->toBe(2)
        ->and($quote->fresh()->source_id)->toBe(2)
        ->and($untouched->fresh()->source_id)->toBe(2)
        ->and(Source::query()->whereIn('id', [9, 11, 14, 15])->exists())->toBeFalse()
        ->and(Source::query()->whereIn('id', [1, 2, 12])->count())->toBe(3);
});

it('changes nothing on a second run', function (): void {
    $registry = Registry::factory()->create(['source_id' => 11]);

    $this->seed(SourcesDeduplicationSeeder::class);
    $this->seed(SourcesDeduplicationSeeder::class);

    expect($registry->fresh()->source_id)->toBe(1)
        ->and(Source::query()->count())->toBe(3);
});

it('stops without changes when an id names another source', function (): void {
    Source::query()->whereKey(12)->update(['name' => 'Altro']);
    $registry = Registry::factory()->create(['source_id' => 14]);

    expect(fn () => $this->seed(SourcesDeduplicationSeeder::class))->toThrow(RuntimeException::class);

    expect($registry->fresh()->source_id)->toBe(14)
        ->and(Source::query()->whereKey(14)->exists())->toBeTrue();
});
