<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use App\Models\User;

/**
 * Validates a `document_layouts.config` tree against the CLOSED allow-list
 * frozen in spec 0069's data_contract (config_schema): 7 block types, 3
 * zones, every enum/limit declared there — unknown keys are rejected, values
 * are checked, but nothing here interpolates or executes the tree (it stays
 * plain data end to end, per the spec's `constraints`).
 *
 * Mirrors AttributeLayoutValidator's split (shape here, allow-list-against-
 * live-data in the same call), but a DIFFERENT failure contract deliberately:
 * this returns a plain `path => message` array and never throws
 * ValidationException — the caller (a wave-2 FormRequest's `withValidator`)
 * decides how to surface it. Building the whole tree is delegated to
 * DocumentLayoutBlockValidator (per-block dispatch) and its own two
 * sub-validators for `table`/`products_table` (engineering.md §6 file-size
 * split — see the docblocks on those classes for why each was split out).
 */
final class DocumentLayoutConfigValidator
{
    /** @var array<int, string> */
    private const array TOP_LEVEL_KEYS = ['version', 'page', 'header', 'body', 'footer'];

    /** @var array<int, string> */
    private const array ZONE_KEYS = ['header', 'body', 'footer'];

    /** @var array<int, string> */
    private const array PAGE_KEYS = ['format', 'orientation', 'margins', 'default_font'];

    /** @var array<int, string> */
    private const array ORIENTATION_VALUES = ['portrait', 'landscape'];

    /** @var array<int, string> */
    private const array MARGIN_SIDES = ['top', 'right', 'bottom', 'left'];

    public function __construct(
        private readonly DocumentLayoutBlockValidator $blockValidator,
        private readonly DocumentLayoutVariableCatalog $variableCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string> path => message, empty when $config is valid.
     */
    public function validate(array $config, ?DocumentLayout $layout, DocumentLayoutModule $module, User $actor): array
    {
        $errors = new DocumentLayoutConfigErrorBag;

        // Step 1: byte-size guard first — everything past this point is
        // meaningless noise on an already-oversized tree.
        if (strlen((string) json_encode($config)) > DocumentLayoutConfigLimits::MAX_CONFIG_BYTES) {
            $errors->add('config', 'The config exceeds the maximum size of '.DocumentLayoutConfigLimits::MAX_CONFIG_BYTES.' bytes.');

            return $errors->all();
        }

        // Step 2: top-level shape (unknown top-level key, `version`).
        ConfigShapeAssertions::assertKnownKeys('config', $config, self::TOP_LEVEL_KEYS, $errors);

        if (($config['version'] ?? null) !== 1) {
            $errors->add('config.version', 'The config version must be 1.');
        }

        // Step 3: the page block.
        $this->assertPage($config['page'] ?? null, $errors);

        // Step 4: the three zones, each a list of blocks — `totals.rows[].variable`
        // is checked against the catalogue's `totals` tokens, resolved once here.
        $totalsTokens = $this->variableCatalog->totalsVariableTokens($module);

        foreach (self::ZONE_KEYS as $zone) {
            $this->assertZone($zone, $config[$zone] ?? null, $layout, $totalsTokens, $errors);
        }

        return $errors->all();
    }

    private function assertPage(mixed $page, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($page)) {
            $errors->add('config.page', 'The page block is required.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys('config.page', $page, self::PAGE_KEYS, $errors);

        if (($page['format'] ?? null) !== 'A4') {
            $errors->add('config.page.format', 'The page format must be "A4".');
        }

        if (! in_array($page['orientation'] ?? null, self::ORIENTATION_VALUES, true)) {
            $errors->add('config.page.orientation', 'The page orientation must be "portrait" or "landscape".');
        }

        $this->assertMargins($page['margins'] ?? null, $errors);
        $this->assertDefaultFont($page['default_font'] ?? null, $errors);
    }

    private function assertMargins(mixed $margins, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($margins)) {
            $errors->add('config.page.margins', 'The page margins are required.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys('config.page.margins', $margins, self::MARGIN_SIDES, $errors);

        foreach (self::MARGIN_SIDES as $side) {
            ConfigShapeAssertions::assertIntInRange("config.page.margins.{$side}", $margins[$side] ?? null, 0, DocumentLayoutConfigLimits::MARGIN_TWIPS_MAX, $errors);
        }
    }

    private function assertDefaultFont(mixed $defaultFont, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($defaultFont)) {
            $errors->add('config.page.default_font', 'The page default font is required.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys('config.page.default_font', $defaultFont, ['family', 'size', 'color'], $errors);

        if (! is_string($defaultFont['family'] ?? null) || $defaultFont['family'] === '') {
            $errors->add('config.page.default_font.family', 'The default font family is required.');
        }

        ConfigShapeAssertions::assertIntInRange('config.page.default_font.size', $defaultFont['size'] ?? null, DocumentLayoutConfigLimits::FONT_SIZE_MIN, DocumentLayoutConfigLimits::FONT_SIZE_MAX, $errors);
        ConfigShapeAssertions::assertHexColor('config.page.default_font.color', $defaultFont['color'] ?? null, $errors);
    }

    /**
     * @param  array<int, string>  $totalsTokens
     */
    private function assertZone(string $zone, mixed $zoneValue, ?DocumentLayout $layout, array $totalsTokens, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_array($zoneValue)) {
            $errors->add("config.{$zone}", 'The zone must be an object.');

            return;
        }

        ConfigShapeAssertions::assertKnownKeys("config.{$zone}", $zoneValue, ['blocks'], $errors);

        $blocks = $zoneValue['blocks'] ?? null;

        if (! is_array($blocks) || ! array_is_list($blocks)) {
            $errors->add("config.{$zone}.blocks", 'The blocks list must be an array.');

            return;
        }

        if (count($blocks) > DocumentLayoutConfigLimits::MAX_BLOCKS_PER_ZONE) {
            $errors->add("config.{$zone}.blocks", 'Too many blocks (max '.DocumentLayoutConfigLimits::MAX_BLOCKS_PER_ZONE.').');
        }

        foreach ($blocks as $index => $block) {
            $this->blockValidator->validate("config.{$zone}.blocks.{$index}", $zone, $block, $layout, $totalsTokens, $errors);
        }
    }
}
