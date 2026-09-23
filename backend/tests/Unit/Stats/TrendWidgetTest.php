<?php

use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\TrendPoint;
use App\Stats\Widgets\TrendWidget;

/**
 * Unit coverage of the `chart`/`tone` fields added by spec 0152, D-1/D-3:
 * the enum default, the explicit override, and the tone range guard —
 * everything the definitions rely on but that the Feature suite (real HTTP
 * round-trip) does not exercise in isolation.
 */
it('defaults to area / tone 1 when a definition does not say otherwise', function () {
    $widget = new TrendWidget(key: 'trend', label: 'domain.stats.trend', points: []);

    expect($widget->chart)->toBe(TrendChart::Area)
        ->and($widget->tone)->toBe(1)
        ->and($widget->toArray())->toMatchArray(['chart' => 'area', 'tone' => 1]);
});

it('serializes an explicit chart/tone into the widget JSON', function () {
    $widget = new TrendWidget(
        key: 'trend',
        label: 'domain.stats.trend',
        points: [new TrendPoint('2026-09', 3)],
        chart: TrendChart::Line,
        tone: 4,
    );

    expect($widget->toArray())->toMatchArray(['chart' => 'line', 'tone' => 4]);
});

it('rejects a tone outside 1..5', function (int $tone) {
    new TrendWidget(key: 'trend', label: 'domain.stats.trend', points: [], tone: $tone);
})->with([0, 6, -1])->throws(InvalidArgumentException::class);
