<?php

declare(strict_types=1);

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutConfigValidator;

// Shared builder/assertion helpers for the DocumentLayoutConfigValidator test
// suite (spec 0069), split across DocumentLayoutConfigValidatorTest,
// *ProductsTableTest, *ImageTest and *LimitsTest (engineering.md §6 file-size
// split). NOT itself a test file (no *Test.php suffix, so Pest does not
// collect it) — each sibling file pulls it in with `require_once`, which is
// idempotent across however many of those files a single Pest run loads
// (unlike a same-name `function` declared directly in each file, which
// collides the moment Pest requires a second file defining it).

if (! function_exists('dlBaseConfig')) {
    /**
     * @param  array<int, array<string, mixed>>  $headerBlocks
     * @param  array<int, array<string, mixed>>  $bodyBlocks
     * @param  array<int, array<string, mixed>>  $footerBlocks
     * @param  array<string, mixed>  $pageOverrides
     * @return array<string, mixed>
     */
    function dlBaseConfig(array $headerBlocks = [], array $bodyBlocks = [], array $footerBlocks = [], array $pageOverrides = []): array
    {
        return [
            'version' => 1,
            'page' => array_merge([
                'format' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 1134, 'right' => 1134, 'bottom' => 1134, 'left' => 1134],
                'default_font' => ['family' => 'Arial', 'size' => 11, 'color' => '000000'],
            ], $pageOverrides),
            'header' => ['blocks' => $headerBlocks],
            'body' => ['blocks' => $bodyBlocks],
            'footer' => ['blocks' => $footerBlocks],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlTextBlock(array $overrides = []): array
    {
        return array_merge(['id' => 'b1', 'type' => 'text', 'align' => 'left', 'space_before' => 0, 'space_after' => 0, 'line_height' => 1.0, 'runs' => []], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlRun(array $overrides = []): array
    {
        return array_merge(['text' => 'Hello', 'field' => null, 'bold' => false, 'italic' => false, 'underline' => false, 'font' => null, 'size' => null, 'color' => null], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlSpacerBlock(array $overrides = []): array
    {
        return array_merge(['id' => 'sp1', 'type' => 'spacer', 'height' => 10], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlDividerBlock(array $overrides = []): array
    {
        return array_merge(['id' => 'dv1', 'type' => 'divider', 'width_pct' => 100, 'thickness' => 4, 'color' => '000000', 'space_before' => 0, 'space_after' => 0], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlTableBlock(array $overrides = []): array
    {
        return array_merge([
            'id' => 't1', 'type' => 'table', 'width_pct' => 100, 'borders' => null,
            'columns' => [['width_pct' => 100]],
            'rows' => [['is_header' => false, 'cells' => [['col_span' => 1, 'background' => null, 'vertical_align' => 'top', 'blocks' => [dlTextBlock()]]]]],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlProductsTableBlock(array $overrides = []): array
    {
        return array_merge([
            'id' => 'pt1', 'type' => 'products_table', 'source' => 'offer_lines', 'width_pct' => 100,
            'borders' => null, 'show_header' => true, 'header_background' => null, 'empty_text' => null,
            'columns' => [['lines' => [['keys' => ['code'], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null]], 'label' => 'Code', 'width_pct' => 100, 'align' => 'left']],
            'totals' => ['show' => false, 'rows' => []],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function dlImageBlock(array $overrides = []): array
    {
        return array_merge(['id' => 'img1', 'type' => 'image', 'attachment_id' => 1, 'width' => 100, 'height' => 100, 'align' => 'left', 'wrap' => 'inline'], $overrides);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    function dlValidate(array $config, ?DocumentLayout $layout = null): array
    {
        return app(DocumentLayoutConfigValidator::class)->validate($config, $layout, DocumentLayoutModule::Quotes, User::factory()->create());
    }
}
