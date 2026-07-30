<?php

declare(strict_types=1);

use App\Services\DocumentLayouts\DocumentLayoutConfigLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/DocumentLayoutConfigValidatorTestSupport.php';

// spec 0069 — DocumentLayoutConfigValidator: dimensional limits (AC-035).
// Split out of DocumentLayoutConfigValidatorTest for file size
// (engineering.md §6). Shared builder helpers (dlBaseConfig, dlValidate, ...)
// live in DocumentLayoutConfigValidatorTestSupport.php.

it('rejects a config whose serialized size exceeds 256 KB (AC-035)', function () {
    $hugeRuns = array_fill(0, 60, dlRun(['text' => str_repeat('a', DocumentLayoutConfigLimits::MAX_RUN_CHARS)]));
    $config = dlBaseConfig(bodyBlocks: [dlTextBlock(['runs' => $hugeRuns])]);

    expect(strlen((string) json_encode($config)))->toBeGreaterThan(DocumentLayoutConfigLimits::MAX_CONFIG_BYTES);

    $errors = dlValidate($config);
    expect($errors)->toHaveKey('config');
});

it('rejects more than 200 blocks in a single zone (AC-035)', function () {
    $blocks = array_map(static fn (int $i): array => dlSpacerBlock(['id' => "sp{$i}"]), range(1, DocumentLayoutConfigLimits::MAX_BLOCKS_PER_ZONE + 1));
    $config = dlBaseConfig(bodyBlocks: $blocks);

    expect(dlValidate($config))->toHaveKey('config.body.blocks');
});

it('rejects more than 200 runs in a single block (AC-035)', function () {
    $runs = array_fill(0, DocumentLayoutConfigLimits::MAX_RUNS_PER_BLOCK + 1, dlRun());
    $config = dlBaseConfig(bodyBlocks: [dlTextBlock(['runs' => $runs])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.runs');
});

it('rejects a run longer than 5000 characters (AC-035)', function () {
    $longText = str_repeat('a', DocumentLayoutConfigLimits::MAX_RUN_CHARS + 1);
    $config = dlBaseConfig(bodyBlocks: [dlTextBlock(['runs' => [dlRun(['text' => $longText])]])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.runs.0.text');
});

it('rejects more than 200 table rows (AC-035)', function () {
    $rows = array_fill(0, DocumentLayoutConfigLimits::MAX_TABLE_ROWS + 1, ['is_header' => false, 'cells' => [['col_span' => 1, 'background' => null, 'vertical_align' => 'top', 'blocks' => [dlTextBlock()]]]]);
    $table = ['id' => 't1', 'type' => 'table', 'width_pct' => 100, 'borders' => null, 'columns' => [['width_pct' => 100]], 'rows' => $rows];
    $config = dlBaseConfig(bodyBlocks: [$table]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.rows');
});

it('rejects more than 12 table columns (AC-035)', function () {
    $columns = array_fill(0, DocumentLayoutConfigLimits::MAX_TABLE_COLUMNS + 1, ['width_pct' => 5]);
    $table = ['id' => 't1', 'type' => 'table', 'width_pct' => 100, 'borders' => null, 'columns' => $columns, 'rows' => [['is_header' => false, 'cells' => [['col_span' => 1, 'background' => null, 'vertical_align' => 'top', 'blocks' => [dlTextBlock()]]]]]];
    $config = dlBaseConfig(bodyBlocks: [$table]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.columns');
});

it('rejects more than 9 product columns (AC-035)', function () {
    $columns = array_map(
        static fn (int $i): array => ['lines' => [['keys' => ['code'], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null]], 'label' => "Col {$i}", 'width_pct' => 10, 'align' => 'left'],
        range(1, DocumentLayoutConfigLimits::MAX_PRODUCT_COLUMNS + 1),
    );
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['columns' => $columns])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.columns');
});

it('rejects more than 10 totals rows (AC-035)', function () {
    $rows = array_map(
        static fn (int $i): array => ['label' => "Row {$i}", 'variable' => '{totals.revenue_net}', 'bold' => false],
        range(1, DocumentLayoutConfigLimits::MAX_TOTALS_ROWS + 1),
    );
    $config = dlBaseConfig(bodyBlocks: [dlProductsTableBlock(['totals' => ['show' => true, 'rows' => $rows]])]);

    expect(dlValidate($config))->toHaveKey('config.body.blocks.0.totals.rows');
});
