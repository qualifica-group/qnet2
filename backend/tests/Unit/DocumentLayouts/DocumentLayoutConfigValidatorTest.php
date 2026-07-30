<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/DocumentLayoutConfigValidatorTestSupport.php';

// spec 0069 — DocumentLayoutConfigValidator: top-level shape, page block,
// text/run rules, table cells, divider, and the AC-037 "exactly at the
// maximum" acceptance case. products_table-specific and image-specific cases
// live in the sibling *ProductsTableTest / *ImageTest files (file-size split,
// engineering.md §6). Shared builder helpers (dlBaseConfig, dlValidate, ...)
// live in DocumentLayoutConfigValidatorTestSupport.php.

// ---------------------------------------------------------------------------
// AC-030 — top-level shape, page block, enums/limits
// ---------------------------------------------------------------------------

it('accepts a well-formed minimal config (baseline)', function () {
    expect(dlValidate(dlBaseConfig()))->toBe([]);
});

it('rejects a missing version (AC-030)', function () {
    $config = dlBaseConfig();
    unset($config['version']);

    expect(dlValidate($config))->toHaveKey('config.version');
});

it('rejects a version different from 1 (AC-030)', function () {
    expect(dlValidate(array_merge(dlBaseConfig(), ['version' => 2])))->toHaveKey('config.version');
});

it('rejects an unknown zone at the top level (AC-030)', function () {
    $config = dlBaseConfig();
    $config['sidebar'] = ['blocks' => []];

    expect(dlValidate($config))->toHaveKey('config.sidebar');
});

it('rejects a block with an unknown type (AC-030)', function () {
    $config = dlBaseConfig(bodyBlocks: [['id' => 'b1', 'type' => 'carousel']]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.type');
});

it('rejects an unknown key inside a block (AC-030)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlTextBlock(['bogus_key' => 'x'])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.bogus_key');
});

it('rejects an orientation outside the enum (AC-030)', function () {
    $config = dlBaseConfig(pageOverrides: ['orientation' => 'diagonal']);

    expect(dlValidate($config))->toHaveKey('config.page.orientation');
});

it('rejects a negative margin and a margin above 5670 (AC-030)', function () {
    $negative = dlBaseConfig(pageOverrides: ['margins' => ['top' => -1, 'right' => 1134, 'bottom' => 1134, 'left' => 1134]]);
    $tooLarge = dlBaseConfig(pageOverrides: ['margins' => ['top' => 5671, 'right' => 1134, 'bottom' => 1134, 'left' => 1134]]);

    expect(dlValidate($negative))->toHaveKey('config.page.margins.top')
        ->and(dlValidate($tooLarge))->toHaveKey('config.page.margins.top');
});

it('rejects a default font size outside 6..72 (AC-030)', function () {
    $tooSmall = dlBaseConfig(pageOverrides: ['default_font' => ['family' => 'Arial', 'size' => 5, 'color' => '000000']]);
    $tooLarge = dlBaseConfig(pageOverrides: ['default_font' => ['family' => 'Arial', 'size' => 73, 'color' => '000000']]);

    expect(dlValidate($tooSmall))->toHaveKey('config.page.default_font.size')
        ->and(dlValidate($tooLarge))->toHaveKey('config.page.default_font.size');
});

it('rejects a non-hex color, both "#fff" and a color name (AC-030)', function () {
    $hash = dlBaseConfig(pageOverrides: ['default_font' => ['family' => 'Arial', 'size' => 11, 'color' => '#fff']]);
    $named = dlBaseConfig(pageOverrides: ['default_font' => ['family' => 'Arial', 'size' => 11, 'color' => 'rosso']]);

    expect(dlValidate($hash))->toHaveKey('config.page.default_font.color')
        ->and(dlValidate($named))->toHaveKey('config.page.default_font.color');
});

it('rejects a text block align outside the enum (AC-030)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlTextBlock(['align' => 'diagonal'])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.align');
});

it('rejects a line_height outside 1.0..3.0 (AC-030)', function () {
    $tooSmall = dlBaseConfig(bodyBlocks: [dlTextBlock(['line_height' => 0.5])]);
    $tooLarge = dlBaseConfig(bodyBlocks: [dlTextBlock(['line_height' => 3.5])]);

    expect(dlValidate($tooSmall))->toHaveKey('config.body.blocks.0.line_height')
        ->and(dlValidate($tooLarge))->toHaveKey('config.body.blocks.0.line_height');
});

// ---------------------------------------------------------------------------
// AC-034 — a table cell may only contain `text` blocks
// ---------------------------------------------------------------------------

it('rejects a table cell containing a non-text block (AC-034)', function () {
    $table = dlTableBlock([
        'rows' => [['is_header' => false, 'cells' => [['col_span' => 1, 'background' => null, 'vertical_align' => 'top', 'blocks' => [['id' => 'img', 'type' => 'image']]]]]],
    ]);
    $config = dlBaseConfig(bodyBlocks: [$table]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.rows.0.cells.0.blocks.0.type');
});

it('accepts a table cell containing a text block', function () {
    $config = dlBaseConfig(bodyBlocks: [dlTableBlock()]);

    expect(dlValidate($config))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-036 — runs[].field
// ---------------------------------------------------------------------------

it('accepts runs[].field = "page"/"total_pages" in every zone (AC-036)', function () {
    $footerBlock = dlTextBlock(['id' => 'f1', 'runs' => [dlRun(['field' => 'page', 'text' => 'ignored'])]]);
    $headerBlock = dlTextBlock(['id' => 'h1', 'runs' => [dlRun(['field' => 'total_pages', 'text' => 'ignored'])]]);
    $config = dlBaseConfig(headerBlocks: [$headerBlock], footerBlocks: [$footerBlock]);

    expect(dlValidate($config))->toBe([]);
});

it('rejects a runs[].field value outside page/total_pages (AC-036)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlTextBlock(['runs' => [dlRun(['field' => 'section'])]])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.runs.0.field');
});

// ---------------------------------------------------------------------------
// AC-039b — divider block (D-11)
// ---------------------------------------------------------------------------

it('accepts a valid divider block (AC-039b)', function () {
    expect(dlValidate(dlBaseConfig(bodyBlocks: [dlDividerBlock()])))->toBe([]);
});

it('rejects a divider width_pct of 0 or above 100 (AC-039b)', function () {
    $zero = dlBaseConfig(bodyBlocks: [dlDividerBlock(['width_pct' => 0])]);
    $tooLarge = dlBaseConfig(bodyBlocks: [dlDividerBlock(['width_pct' => 101])]);

    expect(dlValidate($zero))->toHaveKey('config.body.blocks.0.width_pct')
        ->and(dlValidate($tooLarge))->toHaveKey('config.body.blocks.0.width_pct');
});

it('rejects a divider thickness of 0 (AC-039b)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlDividerBlock(['thickness' => 0])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.thickness');
});

it('rejects a divider color "#000" (3-digit, with hash) (AC-039b)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlDividerBlock(['color' => '#000'])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.color');
});

// ---------------------------------------------------------------------------
// AC-037 — exactly-at-the-maximum acceptance, no off-by-one
// ---------------------------------------------------------------------------

it('accepts a config at exactly the maximum: 200 blocks, 9 product columns, margin 5670, font size 72 (AC-037)', function () {
    $spacers = array_map(static fn (int $i): array => dlSpacerBlock(['id' => "sp{$i}"]), range(1, 199));

    $columns = array_map(
        static fn (int $i): array => ['lines' => [['keys' => ['code'], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null]], 'label' => "Col {$i}", 'width_pct' => 10, 'align' => 'left'],
        range(1, 9),
    );

    $productsTable = dlProductsTableBlock(['columns' => $columns]);

    $config = dlBaseConfig(
        bodyBlocks: [...$spacers, $productsTable],
        pageOverrides: [
            'margins' => ['top' => 5670, 'right' => 5670, 'bottom' => 5670, 'left' => 5670],
            'default_font' => ['family' => 'Arial', 'size' => 72, 'color' => '000000'],
        ],
    );

    expect(count($config['body']['blocks']))->toBe(200)
        ->and(dlValidate($config))->toBe([]);
});
