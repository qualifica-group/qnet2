<?php

declare(strict_types=1);

use App\Support\Amounts\LineAmountCalculator;

/**
 * Cases shared with the frontend (docs/specs/0253-amount-cases.json, spec 0253).
 *
 * @return array<string, array{0: array<string, mixed>}>
 */
function amountCases(): array
{
    $file = json_decode((string) file_get_contents(__DIR__.'/../../../../../docs/specs/0253-amount-cases.json'), true, flags: JSON_THROW_ON_ERROR);
    $cases = [];

    foreach ($file['cases'] as $case) {
        $cases[$case['name']] = [$case];
    }

    return $cases;
}

it('computes the shared cases', function (array $case): void {
    $engine = new LineAmountCalculator;
    $scale = $case['lines'][0]['scale'];
    $lines = array_map(fn (array $line): array => [
        'quantity' => $line['quantity'],
        'unit_price' => $line['unit_price'],
        'vat_rate' => $line['vat_rate'],
    ], $case['lines']);

    if ($case['mode'] === 'per_rate') {
        $actual = $engine->documentPerRate($lines, $scale);
    } else {
        $computed = array_map(fn (array $line): array => $engine->line($line['quantity'], $line['unit_price'], $line['vat_rate'], $scale), $lines);
        $actual = ['lines' => $computed] + $engine->sum($computed);
    }

    expect($actual['lines'])->toBe($case['expected']['lines'])
        ->and($actual['net'])->toBe($case['expected']['net'])
        ->and($actual['vat'])->toBe($case['expected']['vat'])
        ->and($actual['total'])->toBe($case['expected']['total'])
        ->and(array_sum(array_map(fn (array $line): float => (float) $line['vat'], $actual['lines'])))->toEqualWithDelta((float) $actual['vat'], 0.0001);
})->with(amountCases());

it('rounds amounts to cents half away from zero', function (): void {
    $engine = new LineAmountCalculator;

    expect($engine->roundToCents('10.005'))->toBe('10.01')
        ->and($engine->roundToCents('10.004'))->toBe('10.00')
        ->and($engine->roundToCents(0.1 + 0.2))->toBe('0.30')
        ->and($engine->roundToCents('-10.005'))->toBe('-10.01');
});

it('tells a partial payment on the rounded amount', function (): void {
    $engine = new LineAmountCalculator;

    expect($engine->isPartial('99.994', '100.00'))->toBeTrue()
        ->and($engine->isPartial('99.995', '100.00'))->toBeFalse()
        ->and($engine->isPartial('100', '100.00'))->toBeFalse();
});
