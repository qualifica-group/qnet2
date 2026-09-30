<?php

declare(strict_types=1);

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The resolver-labelled top-N (stress-test S6): its cost guarantee is that
 * the label resolver sees only the ranked top-N ids, never every referenced
 * parent — the whole point of ranking on the bare FK first.
 */
it('hands the label resolver only the top-N ids, ranked by count', function (): void {
    [$first, $second, $third] = OperationalSite::factory()->count(3)->create();
    Opportunity::factory()->count(3)->create(['operational_site_id' => $first->id]);
    Opportunity::factory()->count(2)->create(['operational_site_id' => $second->id]);
    Opportunity::factory()->create(['operational_site_id' => $third->id]);

    $received = null;
    $items = Aggregates::topRelatedLabelledBy(
        query: DB::table('opportunities'),
        foreignKey: 'opportunities.operational_site_id',
        limit: 2,
        labels: function (array $ids) use (&$received): array {
            $received = $ids;

            return array_combine($ids, array_map(static fn (int $id): string => "site {$id}", $ids));
        },
    );

    expect($received)->toBe([$first->id, $second->id])
        ->and(array_map(static fn (DistributionItem $item): array => $item->toArray(), $items))->toBe([
            ['key' => (string) $first->id, 'label' => "site {$first->id}", 'value' => 3, 'color' => null],
            ['key' => (string) $second->id, 'label' => "site {$second->id}", 'value' => 2, 'color' => null],
        ]);
});

it('drops a ranked id the resolver cannot label, like the inner join of topRelated()', function (): void {
    [$labelled, $unlabelled] = OperationalSite::factory()->count(2)->create();
    Opportunity::factory()->count(2)->create(['operational_site_id' => $unlabelled->id]);
    Opportunity::factory()->create(['operational_site_id' => $labelled->id]);

    $items = Aggregates::topRelatedLabelledBy(
        query: DB::table('opportunities'),
        foreignKey: 'opportunities.operational_site_id',
        limit: 10,
        labels: static fn (array $ids): array => [$labelled->id => 'Via Roma 1'],
    );

    expect($items)->toHaveCount(1)
        ->and($items[0]->key)->toBe((string) $labelled->id)
        ->and($items[0]->value)->toBe(1);
});
