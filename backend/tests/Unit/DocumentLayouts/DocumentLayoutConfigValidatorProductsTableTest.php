<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/DocumentLayoutConfigValidatorTestSupport.php';

// spec 0069 — DocumentLayoutConfigValidator: `products_table` block
// (AC-031/AC-032/AC-039). Split out of DocumentLayoutConfigValidatorTest for
// file size (engineering.md §6). Shared builder helpers (dlBaseConfig,
// dlValidate, ...) live in DocumentLayoutConfigValidatorTestSupport.php.

it('accepts a valid products_table block (baseline)', function () {
    expect(dlValidate(dlBaseConfig(bodyBlocks: [dlProductsTableBlock()])))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-031 — `discount` is not in the ColumnKey allow-list (D-4)
// ---------------------------------------------------------------------------

it('rejects "discount" among a column\'s lines[].keys (AC-031)', function () {
    $columns = [['lines' => [['keys' => ['discount'], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null]], 'label' => 'Discount', 'width_pct' => 100, 'align' => 'left']];
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['columns' => $columns])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.columns.0.lines.0.keys.0');
});

// ---------------------------------------------------------------------------
// AC-032 — `source` enum, `totals.rows[].variable`
// ---------------------------------------------------------------------------

it('rejects a products_table source outside offer_lines/cost_lines (AC-032)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['source' => 'archived_lines'])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.source');
});

it('rejects a totals.rows[].variable that is not a real "totals" category variable (AC-032)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock([
        'totals' => ['show' => true, 'rows' => [['label' => 'Total', 'variable' => '{totals.not_a_real_key}', 'bold' => true]]],
    ])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.totals.rows.0.variable');
});

it('accepts a totals.rows[].variable that IS a real "totals" category variable (AC-032)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock([
        'totals' => ['show' => true, 'rows' => [['label' => 'Net revenue', 'variable' => '{totals.revenue_net}', 'bold' => true]]],
    ])]);

    expect(dlValidate($config))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-039 — columns[].lines (D-11)
// ---------------------------------------------------------------------------

it('accepts a column with 2 lines, the first with keys ["code","name"] and separator "-" (AC-039)', function () {
    $columns = [[
        'lines' => [
            ['keys' => ['code', 'name'], 'separator' => '-', 'bold' => false, 'italic' => false, 'size' => null],
            ['keys' => ['description'], 'separator' => '', 'bold' => false, 'italic' => true, 'size' => null],
        ],
        'label' => 'Product', 'width_pct' => 100, 'align' => 'left',
    ]];

    expect(dlValidate(dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['columns' => $columns])])))->toBe([]);
});

it('rejects a column with more than 3 lines (AC-039)', function () {
    $lines = array_fill(0, 4, ['keys' => ['code'], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null]);
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['columns' => [['lines' => $lines, 'label' => 'X', 'width_pct' => 100, 'align' => 'left']]])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.columns.0.lines');
});

it('rejects a line with more than 4 keys (AC-039)', function () {
    $columns = [['lines' => [['keys' => ['code', 'name', 'description', 'quantity', 'unit_price'], 'separator' => ' ', 'bold' => false, 'italic' => false, 'size' => null]], 'label' => 'X', 'width_pct' => 100, 'align' => 'left']];
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['columns' => $columns])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.columns.0.lines.0.keys');
});

it('rejects an empty lines[] on a column (AC-039)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['columns' => [['lines' => [], 'label' => 'X', 'width_pct' => 100, 'align' => 'left']]])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.columns.0.lines');
});

it('rejects an empty keys[] on a line (AC-039)', function () {
    $columns = [['lines' => [['keys' => [], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null]], 'label' => 'X', 'width_pct' => 100, 'align' => 'left']];
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['columns' => $columns])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.columns.0.lines.0.keys');
});
