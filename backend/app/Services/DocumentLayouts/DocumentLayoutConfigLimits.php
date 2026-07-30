<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

/**
 * The dimensional limits frozen in spec 0069's data_contract (config_schema,
 * "LIMITI DIMENSIONALI"): single source of truth for
 * DocumentLayoutConfigValidator and its split validators. The frontend
 * contract module (wave 2, `features/document-layouts/`) mirrors these same
 * numbers under its own constants — this class is the backend half only.
 */
final class DocumentLayoutConfigLimits
{
    /** Max size, in bytes, of the `config` tree once JSON-serialized. */
    public const int MAX_CONFIG_BYTES = 262144;

    public const int MAX_BLOCKS_PER_ZONE = 200;

    public const int MAX_RUNS_PER_BLOCK = 200;

    public const int MAX_RUN_CHARS = 5000;

    public const int MAX_TABLE_ROWS = 200;

    public const int MAX_TABLE_COLUMNS = 12;

    public const int MAX_PRODUCT_COLUMNS = 9;

    public const int MAX_LINES_PER_PRODUCT_COLUMN = 3;

    public const int MAX_KEYS_PER_LINE = 4;

    public const int MAX_TOTALS_ROWS = 10;

    /**
     * Max images an uploaded layout may own — consumed by the (wave 2)
     * DocumentLayoutImageService on upload, not by this config validator
     * (a `config` blob never counts images, it only references
     * `attachment_id`s already stored). Declared here anyway so no magic
     * value is duplicated between the two.
     */
    public const int MAX_IMAGES_PER_LAYOUT = 5;

    public const int MARGIN_TWIPS_MAX = 5670;

    public const int FONT_SIZE_MIN = 6;

    public const int FONT_SIZE_MAX = 72;

    /** Max side, in points, of an image block (width or height). */
    public const int MAX_IMAGE_POINTS = 1200;
}
