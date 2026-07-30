<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Models\DocumentLayout;

/**
 * Per-block dispatch for DocumentLayoutConfigValidator (spec 0069): validates
 * one block's shape against its type's allow-listed keys. Handles `text`
 * (also reused, via validateTextBlock(), for the text-only cells of a
 * `table` block — D "vincolo deliberato: una cella contiene SOLO blocchi
 * text"), `image`, `page_break`, `spacer` and `divider` (D-11) directly;
 * `table` and `products_table` are delegated to their own validator classes
 * (engineering.md §6 split — both need enough of their own shape/limit rules
 * to be file-sized modules on their own).
 */
final class DocumentLayoutBlockValidator
{
    /** @var array<int, string> */
    private const array ALLOWED_TYPES = ['text', 'image', 'table', 'products_table', 'page_break', 'spacer', 'divider'];

    /** @var array<int, string> */
    private const array TEXT_KEYS = ['id', 'type', 'align', 'space_before', 'space_after', 'line_height', 'runs'];

    /** @var array<int, string> */
    private const array RUN_KEYS = ['text', 'field', 'bold', 'italic', 'underline', 'font', 'size', 'color'];

    /** @var array<int, string> */
    private const array IMAGE_KEYS = ['id', 'type', 'attachment_id', 'width', 'height', 'align', 'wrap'];

    /** @var array<int, string> */
    private const array SIMPLE_KEYS = ['id', 'type'];

    /** @var array<int, string> */
    private const array SPACER_KEYS = ['id', 'type', 'height'];

    /** @var array<int, string> */
    private const array DIVIDER_KEYS = ['id', 'type', 'width_pct', 'thickness', 'color', 'space_before', 'space_after'];

    /** @var array<int, string> */
    private const array PARAGRAPH_ALIGN_VALUES = ['left', 'center', 'right', 'justify'];

    /** @var array<int, string> */
    private const array IMAGE_ALIGN_VALUES = ['left', 'center', 'right'];

    /** @var array<int, string> */
    private const array WRAP_VALUES = ['inline', 'behind_page'];

    /** @var array<int, string> */
    private const array FIELD_VALUES = ['page', 'total_pages'];

    private const string HEADER_ZONE = 'header';

    public function __construct(
        private readonly DocumentLayoutTableBlockValidator $tableValidator,
        private readonly DocumentLayoutProductsTableBlockValidator $productsTableValidator,
    ) {}

    /**
     * @param  array<int, string>  $totalsTokens
     */
    public function validate(string $path, string $zone, mixed $block, ?DocumentLayout $layout, array $totalsTokens, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($block)) {
            $errors->add($path, 'A block must be an object.');

            return;
        }

        ConfigShapeAssertions::assertNonEmptyString("{$path}.id", $block['id'] ?? null, $errors);

        $type = $block['type'] ?? null;

        if (! is_string($type) || ! in_array($type, self::ALLOWED_TYPES, true)) {
            $errors->add("{$path}.type", 'Unknown block type.');

            return;
        }

        match ($type) {
            'text' => $this->validateTextBlock($path, $block, $errors),
            'image' => $this->validateImageBlock($path, $zone, $block, $layout, $errors),
            'table' => $this->tableValidator->validate($path, $block, $this, $errors),
            'products_table' => $this->productsTableValidator->validate($path, $block, $totalsTokens, $errors),
            'page_break' => ConfigShapeAssertions::assertKnownKeys($path, $block, self::SIMPLE_KEYS, $errors),
            'spacer' => $this->validateSpacerBlock($path, $block, $errors),
            'divider' => $this->validateDividerBlock($path, $block, $errors),
        };
    }

    /**
     * Public: also the entry point for a `table` cell's `blocks` (text-only,
     * see class docblock) — called by DocumentLayoutTableBlockValidator.
     *
     * @param  array<string, mixed>  $block
     */
    public function validateTextBlock(string $path, array $block, DocumentLayoutConfigErrorBag $errors): void
    {
        ConfigShapeAssertions::assertKnownKeys($path, $block, self::TEXT_KEYS, $errors);
        ConfigShapeAssertions::assertNullableEnum("{$path}.align", $block['align'] ?? null, self::PARAGRAPH_ALIGN_VALUES, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.space_before", $block['space_before'] ?? null, 0, DocumentLayoutConfigLimits::MARGIN_TWIPS_MAX, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.space_after", $block['space_after'] ?? null, 0, DocumentLayoutConfigLimits::MARGIN_TWIPS_MAX, $errors);
        $this->assertLineHeight("{$path}.line_height", $block['line_height'] ?? null, $errors);
        $this->assertRuns("{$path}.runs", $block['runs'] ?? null, $errors);
    }

    private function assertLineHeight(string $path, mixed $value, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_int($value) && ! is_float($value)) {
            $errors->add($path, 'Must be a number between 1.0 and 3.0.');

            return;
        }

        if ($value < 1.0 || $value > 3.0) {
            $errors->add($path, 'Must be a number between 1.0 and 3.0.');
        }
    }

    private function assertRuns(string $path, mixed $runs, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($runs) || ! array_is_list($runs)) {
            $errors->add($path, 'Must be an array of runs.');

            return;
        }

        if (count($runs) > DocumentLayoutConfigLimits::MAX_RUNS_PER_BLOCK) {
            $errors->add($path, 'Too many runs (max '.DocumentLayoutConfigLimits::MAX_RUNS_PER_BLOCK.').');
        }

        foreach ($runs as $index => $run) {
            $this->assertRun("{$path}.{$index}", $run, $errors);
        }
    }

    private function assertRun(string $path, mixed $run, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($run)) {
            $errors->add($path, 'A run must be an object.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys($path, $run, self::RUN_KEYS, $errors);

        $field = $run['field'] ?? null;

        if ($field !== null && ! in_array($field, self::FIELD_VALUES, true)) {
            $errors->add("{$path}.field", 'Must be null, "page" or "total_pages".');
        }

        if ($field === null) {
            $text = $run['text'] ?? null;

            if (! is_string($text)) {
                $errors->add("{$path}.text", 'Must be a string.');
            } elseif (mb_strlen($text) > DocumentLayoutConfigLimits::MAX_RUN_CHARS) {
                $errors->add("{$path}.text", 'Too long (max '.DocumentLayoutConfigLimits::MAX_RUN_CHARS.' characters).');
            }
        }

        foreach (['bold', 'italic', 'underline'] as $flag) {
            ConfigShapeAssertions::assertBool("{$path}.{$flag}", $run[$flag] ?? null, $errors);
        }

        ConfigShapeAssertions::assertNullableString("{$path}.font", $run['font'] ?? null, $errors);
        ConfigShapeAssertions::assertNullableIntInRange("{$path}.size", $run['size'] ?? null, DocumentLayoutConfigLimits::FONT_SIZE_MIN, DocumentLayoutConfigLimits::FONT_SIZE_MAX, $errors);
        ConfigShapeAssertions::assertNullableHexColor("{$path}.color", $run['color'] ?? null, $errors);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function validateImageBlock(string $path, string $zone, array $block, ?DocumentLayout $layout, DocumentLayoutConfigErrorBag $errors): void
    {
        ConfigShapeAssertions::assertKnownKeys($path, $block, self::IMAGE_KEYS, $errors);
        ConfigShapeAssertions::assertEnum("{$path}.align", $block['align'] ?? null, self::IMAGE_ALIGN_VALUES, $errors);

        $wrap = $block['wrap'] ?? null;
        ConfigShapeAssertions::assertEnum("{$path}.wrap", $wrap, self::WRAP_VALUES, $errors);

        if ($wrap === 'behind_page' && $zone !== self::HEADER_ZONE) {
            $errors->add("{$path}.wrap", 'The "behind_page" wrap is only allowed in the header zone.');
        }

        $height = $block['height'] ?? null;

        if ($wrap === 'behind_page' && $height === null) {
            $errors->add("{$path}.height", 'A "behind_page" image requires an explicit height.');
        } else {
            ConfigShapeAssertions::assertNullableIntInRange("{$path}.height", $height, 1, DocumentLayoutConfigLimits::MAX_IMAGE_POINTS, $errors);
        }

        ConfigShapeAssertions::assertIntInRange("{$path}.width", $block['width'] ?? null, 1, DocumentLayoutConfigLimits::MAX_IMAGE_POINTS, $errors);

        $this->assertImageOwnership($path, $block['attachment_id'] ?? null, $layout, $errors);
    }

    private function assertImageOwnership(string $path, mixed $attachmentId, ?DocumentLayout $layout, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_int($attachmentId)) {
            $errors->add("{$path}.attachment_id", 'Must be an integer.');

            return;
        }

        // On create ($layout === null) no image can belong to a layout that
        // does not exist yet — see this class' constructor docblock's
        // sibling note on DocumentLayoutConfigValidator: an image block with
        // an attachment_id set during create is always an error.
        if ($layout === null) {
            $errors->add("{$path}.attachment_id", 'This layout has no uploaded images yet.');

            return;
        }

        if (! $layout->images()->whereKey($attachmentId)->exists()) {
            $errors->add("{$path}.attachment_id", 'This image does not belong to this layout.');
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function validateSpacerBlock(string $path, array $block, DocumentLayoutConfigErrorBag $errors): void
    {
        ConfigShapeAssertions::assertKnownKeys($path, $block, self::SPACER_KEYS, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.height", $block['height'] ?? null, 1, 200, $errors);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function validateDividerBlock(string $path, array $block, DocumentLayoutConfigErrorBag $errors): void
    {
        ConfigShapeAssertions::assertKnownKeys($path, $block, self::DIVIDER_KEYS, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.width_pct", $block['width_pct'] ?? null, 1, 100, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.thickness", $block['thickness'] ?? null, 1, 96, $errors);
        ConfigShapeAssertions::assertHexColor("{$path}.color", $block['color'] ?? null, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.space_before", $block['space_before'] ?? null, 0, DocumentLayoutConfigLimits::MARGIN_TWIPS_MAX, $errors);
        ConfigShapeAssertions::assertIntInRange("{$path}.space_after", $block['space_after'] ?? null, 0, DocumentLayoutConfigLimits::MARGIN_TWIPS_MAX, $errors);
    }
}
