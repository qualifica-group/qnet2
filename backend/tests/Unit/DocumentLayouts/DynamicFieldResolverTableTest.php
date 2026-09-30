<?php

declare(strict_types=1);

use App\Models\Quote;
use App\Services\DocumentLayouts\Rendering\DynamicFieldResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Spec 0180 AC-016: a table value renders as its summary.

function tableQuote(mixed $value): Quote
{
    // `custom_fields` is a computed accessor over the stored row: pin it to the
    // value under test, since only the resolver's rendering is in scope here.
    $quote = new class extends Quote
    {
        public mixed $tableValue = null;

        public function getCustomFieldsAttribute(): array
        {
            return ['inspections' => $this->tableValue];
        }
    };
    $quote->tableValue = $value;
    $quote->attribute_values = ['inspections' => $value];

    return $quote;
}

it('AC-016: renders the summary as a string, empty when null, never throwing', function (mixed $value, string $expected) {
    $resolver = new DynamicFieldResolver;

    expect($resolver->customField('inspections', tableQuote($value)))->toBe($expected)
        ->and($resolver->quoteAttribute('inspections', tableQuote($value)))->toBe($expected);
})->with([
    'date summary' => [['rows' => [['id' => 'a', 'inspection_date' => '2026-10-12']], 'summary' => '2026-10-12'], '2026-10-12'],
    'numeric summary' => [['rows' => [], 'summary' => 7], '7'],
    'null summary' => [['rows' => [], 'summary' => null], ''],
    'null value' => [null, ''],
]);
