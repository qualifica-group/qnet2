<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\DocumentLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/DocumentLayoutConfigValidatorTestSupport.php';

// spec 0069 — DocumentLayoutConfigValidator: `image` block (AC-033/AC-038).
// Split out of DocumentLayoutConfigValidatorTest for file size
// (engineering.md §6). Shared builder helpers (dlBaseConfig, dlImageBlock,
// dlValidate, ...) live in DocumentLayoutConfigValidatorTestSupport.php.

// ---------------------------------------------------------------------------
// AC-033 — attachment_id ownership
// ---------------------------------------------------------------------------

it('rejects an image block with attachment_id set on CREATE (no layout exists yet) (AC-033)', function () {
    $config = dlBaseConfig(headerBlocks: [dlImageBlock()]);

    expect(dlValidate($config, layout: null))->toHaveKey('config.header.blocks.0.attachment_id');
});

it('rejects an attachment_id that does not belong to this layout (AC-033)', function () {
    $layout = DocumentLayout::factory()->create();
    $otherLayout = DocumentLayout::factory()->create();
    $attachment = $otherLayout->images()->save(Attachment::factory()->make(['collection' => DocumentLayout::IMAGE_COLLECTION]));

    $config = dlBaseConfig(headerBlocks: [dlImageBlock(['attachment_id' => $attachment->id])]);

    expect(dlValidate($config, $layout))->toHaveKey('config.header.blocks.0.attachment_id');
});

it('rejects an attachment_id that does not exist (AC-033)', function () {
    $layout = DocumentLayout::factory()->create();
    $config = dlBaseConfig(headerBlocks: [dlImageBlock(['attachment_id' => 999999])]);

    expect(dlValidate($config, $layout))->toHaveKey('config.header.blocks.0.attachment_id');
});

it('accepts an attachment_id that belongs to this layout, in collection layout_image (AC-033)', function () {
    $layout = DocumentLayout::factory()->create();
    $attachment = $layout->images()->save(Attachment::factory()->make(['collection' => DocumentLayout::IMAGE_COLLECTION]));

    $config = dlBaseConfig(headerBlocks: [dlImageBlock(['attachment_id' => $attachment->id, 'wrap' => 'behind_page'])]);

    expect(dlValidate($config, $layout))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-038 — `wrap` (D-11)
// ---------------------------------------------------------------------------

it('accepts wrap "behind_page" in the header zone with an explicit height (AC-038)', function () {
    $layout = DocumentLayout::factory()->create();
    $attachment = $layout->images()->save(Attachment::factory()->make(['collection' => DocumentLayout::IMAGE_COLLECTION]));

    $config = dlBaseConfig(headerBlocks: [dlImageBlock(['attachment_id' => $attachment->id, 'wrap' => 'behind_page', 'height' => 800])]);

    expect(dlValidate($config, $layout))->toBe([]);
});

it('rejects wrap "behind_page" in the body or footer zone (AC-038)', function () {
    $layout = DocumentLayout::factory()->create();
    $attachment = $layout->images()->save(Attachment::factory()->make(['collection' => DocumentLayout::IMAGE_COLLECTION]));
    $block = dlImageBlock(['attachment_id' => $attachment->id, 'wrap' => 'behind_page', 'height' => 800]);

    $inBody = dlBaseConfig(bodyBlocks: [$block]);
    $inFooter = dlBaseConfig(footerBlocks: [$block]);

    expect(dlValidate($inBody, $layout))->toHaveKey('config.body.blocks.0.wrap')
        ->and(dlValidate($inFooter, $layout))->toHaveKey('config.footer.blocks.0.wrap');
});

it('rejects wrap "behind_page" with height null (AC-038)', function () {
    $layout = DocumentLayout::factory()->create();
    $attachment = $layout->images()->save(Attachment::factory()->make(['collection' => DocumentLayout::IMAGE_COLLECTION]));

    $config = dlBaseConfig(headerBlocks: [dlImageBlock(['attachment_id' => $attachment->id, 'wrap' => 'behind_page', 'height' => null])]);

    expect(dlValidate($config, $layout))->toHaveKey('config.header.blocks.0.height');
});

it('rejects a side above MAX_IMAGE_POINTS (1200) (AC-038)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlImageBlock(['width' => 1201])]);

    expect(dlValidate($config, layout: null))->toHaveKey('config.body.blocks.0.width');
});

it('rejects a wrap value outside inline/behind_page (AC-038)', function () {
    $config = dlBaseConfig(bodyBlocks: [dlImageBlock(['wrap' => 'floating'])]);

    expect(dlValidate($config, layout: null))->toHaveKey('config.body.blocks.0.wrap');
});
