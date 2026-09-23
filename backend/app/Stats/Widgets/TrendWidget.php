<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

use InvalidArgumentException;

/**
 * A time series over the last N months (spec 0026). Empty months are present
 * with value 0 — the series is always dense, so the frontend never has to
 * reconstruct the missing buckets.
 *
 * `chart` is the shape the definition picked for this series (spec 0152,
 * D-3), `tone` the `--chart-{tone}` colour slot (1..5) — both always present,
 * defaulting to `Area`/`1` when a definition does not say otherwise.
 */
final readonly class TrendWidget implements Widget
{
    /** The `--chart-N` colour tokens the frontend palette exposes (D-4). */
    private const int MIN_TONE = 1;

    private const int MAX_TONE = 5;

    /**
     * @param  array<int, TrendPoint>  $points
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $points,
        public StatFormat $format = StatFormat::Number,
        public TrendChart $chart = TrendChart::Area,
        public int $tone = 1,
    ) {
        if ($this->tone < self::MIN_TONE || $this->tone > self::MAX_TONE) {
            $min = self::MIN_TONE;
            $max = self::MAX_TONE;

            throw new InvalidArgumentException("TrendWidget tone must be between {$min} and {$max} (got {$this->tone}).");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'trend',
            'key' => $this->key,
            'label' => $this->label,
            'points' => array_map(
                static fn (TrendPoint $point): array => $point->toArray(),
                $this->points,
            ),
            'format' => $this->format->value,
            'chart' => $this->chart->value,
            'tone' => $this->tone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
