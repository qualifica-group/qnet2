<?php

use App\Migrations\Support\PersonNameSplitter;

// Spec 0189, G-11: the legacy name of a private customer is re-split on the
// first six letters of the tax code (surname code + first-name code).

it('re-splits a legacy name on the tax code', function (?string $first, ?string $last, ?string $taxCode, array $expected) {
    expect((new PersonNameSplitter)->split($first, $last, $taxCode))->toBe($expected);
})->with([
    'inverted surname/first name' => ['CAPOLUPO', 'ELENA', 'CPLMNL99A59F839Q', ['ELENA', 'CAPOLUPO']],
    'first name first, already right' => ['GIUSEPPINA', 'COSENTINO', 'CSNGPP84T69C351V', ['GIUSEPPINA', 'COSENTINO']],
    'first name first, swapped by the legacy' => ['COSENTINO', 'GIUSEPPINA', 'CSNGPP84T69C351V', ['GIUSEPPINA', 'COSENTINO']],
    'compound names, wrong split point' => ['MARCO', 'PIO DI LITTA', 'DLTMCP94A12C034H', ['MARCO PIO', 'DI LITTA']],
    'surname written first' => ['DI', 'LITTA MARCO PIO', 'dltmcp94a12c034h', ['MARCO PIO', 'DI LITTA']],
    'accents and mixed case kept' => ['Nicolò', 'Rossi', 'RSSNCL80A01H501U', ['Nicolò', 'Rossi']],
    'no tax code' => ['CAPOLUPO', 'ELENA', null, ['CAPOLUPO', 'ELENA']],
    'company tax code (VAT digits)' => ['CAPOLUPO', 'ELENA', '01234567890', ['CAPOLUPO', 'ELENA']],
    'tax code of someone else' => ['FRANZESE', 'FILOMENA', 'RSSMRA80A01H501U', ['FRANZESE', 'FILOMENA']],
    'single word' => ['CAPOLUPO', null, 'CPLMNL99A59F839Q', ['CAPOLUPO', null]],
]);
