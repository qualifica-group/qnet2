/**
 * Dimensional limits and per-block-type default factories for the
 * `document_layouts.config` contract (spec 0069 `config_schema`). Split out
 * of `layout-config.ts` to keep that file a pure type contract within the
 * engineering.md §6 size budget. The limits below are the single frontend
 * source of truth, mirrored 1:1 by the backend `DocumentLayoutConfigValidator`
 * and consumed by `layout-config-schema.ts` (Zod) — no magic numbers repeated
 * inline anywhere else.
 */

import type {
  Block,
  DividerBlock,
  DocumentLayoutConfig,
  DocumentLayoutPage,
  DocumentLayoutRun,
  ImageBlock,
  PageBreakBlock,
  ProductColumn,
  ProductColumnLine,
  ProductsTableBlock,
  ProductsTableTotalsRow,
  RunField,
  SpacerBlock,
  TableBlock,
  TableCell,
  TableRow,
  TextBlock,
} from '@/features/document-layouts/layout-config'
import { BLOCK_TYPES, CONFIG_VERSION, type BlockType } from '@/features/document-layouts/layout-config'

// ---------------------------------------------------------------------------
// Dimensional limits — mirrored 1:1 by the backend validator.
// ---------------------------------------------------------------------------

/** Max size in bytes of the serialized `config` JSON (256 KB). */
export const MAX_CONFIG_BYTES = 262144
/** Max blocks allowed inside a single zone (`header`/`body`/`footer`). */
export const MAX_BLOCKS_PER_ZONE = 200
/** Max runs allowed inside a single `text` block. */
export const MAX_RUNS_PER_BLOCK = 200
/** Max characters allowed in a single run's `text`. */
export const MAX_RUN_CHARS = 5000
/** Max rows allowed in a `table` block. */
export const MAX_TABLE_ROWS = 200
/** Max columns allowed in a `table` block. */
export const MAX_TABLE_COLUMNS = 12
/** Max columns allowed in a `products_table` block. */
export const MAX_PRODUCT_COLUMNS = 9
/** Max stacked paragraph lines allowed inside one `products_table` column. */
export const MAX_LINES_PER_PRODUCT_COLUMN = 3
/** Max `ColumnKey`s allowed inside one product column line. */
export const MAX_KEYS_PER_LINE = 4
/** Max rows allowed in a `products_table.totals` block. */
export const MAX_TOTALS_ROWS = 10
/** Max images that may be uploaded to a single layout. */
export const MAX_IMAGES_PER_LAYOUT = 5
/** Min/max page margin, in twips. */
export const MARGIN_TWIPS_MIN = 0
export const MARGIN_TWIPS_MAX = 5670
/** Min/max font size, in points. */
export const FONT_SIZE_MIN = 6
export const FONT_SIZE_MAX = 72
/** Max side (width or height) of an image block, in points. */
export const MAX_IMAGE_POINTS = 1200
/** Min/max `text.line_height` multiplier. */
export const LINE_HEIGHT_MIN = 1.0
export const LINE_HEIGHT_MAX = 3.0
/** Min/max `spacer.height`, in points. */
export const SPACER_HEIGHT_MIN = 1
export const SPACER_HEIGHT_MAX = 200
/** Min/max `divider.thickness`, in eighths of a point. */
export const DIVIDER_THICKNESS_MIN = 1
export const DIVIDER_THICKNESS_MAX = 96
/** Min/max integer percentage for any `width_pct` field. */
export const WIDTH_PCT_MIN = 1
export const WIDTH_PCT_MAX = 100

// ---------------------------------------------------------------------------
// Page defaults.
// ---------------------------------------------------------------------------

/** 1 inch, the conventional starting margin for a new layout. */
const DEFAULT_MARGIN_TWIPS = 1440
const DEFAULT_FONT_FAMILY = 'Calibri'
const DEFAULT_FONT_SIZE = 11
const DEFAULT_FONT_COLOR = '000000'
const DEFAULT_DIVIDER_WIDTH_PCT = 100
/** 1pt, in eighths of a point. */
const DEFAULT_DIVIDER_THICKNESS = 8
const DEFAULT_DIVIDER_SPACING_TWIPS = 120
const DEFAULT_SPACER_HEIGHT = 12

/** A fresh `page` block with a 1-inch margin on every side and the layout's base font. */
export function createDefaultPage(): DocumentLayoutPage {
  return {
    format: 'A4',
    orientation: 'portrait',
    margins: {
      top: DEFAULT_MARGIN_TWIPS,
      right: DEFAULT_MARGIN_TWIPS,
      bottom: DEFAULT_MARGIN_TWIPS,
      left: DEFAULT_MARGIN_TWIPS,
    },
    default_font: {
      family: DEFAULT_FONT_FAMILY,
      size: DEFAULT_FONT_SIZE,
      color: DEFAULT_FONT_COLOR,
    },
  }
}

/**
 * The empty config sent by the metadata-only create form (wave 1, this
 * spec): three empty zones over a default page. The visual editor (wave 2)
 * is what actually populates `header`/`body`/`footer`.
 */
export function createEmptyDocumentLayoutConfig(): DocumentLayoutConfig {
  return {
    version: CONFIG_VERSION,
    page: createDefaultPage(),
    header: { blocks: [] },
    body: { blocks: [] },
    footer: { blocks: [] },
  }
}

// ---------------------------------------------------------------------------
// Per-block-type default factories (spec 0069: "aggiungere un blocco a una
// zona lo appende in coda con i default del suo tipo", AC-120). `image` is
// excluded from the uniform factory: it has no sensible default without an
// `attachment_id` the caller must supply.
// ---------------------------------------------------------------------------

export function createDefaultTextBlock(id: string): TextBlock {
  return {
    id,
    type: 'text',
    align: 'left',
    space_before: 0,
    space_after: 0,
    line_height: 1.0,
    runs: [],
  }
}

export function createDefaultImageBlock(id: string, attachmentId: number): ImageBlock {
  return {
    id,
    type: 'image',
    attachment_id: attachmentId,
    width: 100,
    height: null,
    align: 'left',
    wrap: 'inline',
  }
}

export function createDefaultTableBlock(id: string): TableBlock {
  return {
    id,
    type: 'table',
    width_pct: WIDTH_PCT_MAX,
    borders: null,
    columns: [{ width_pct: WIDTH_PCT_MAX }],
    rows: [],
  }
}

export function createDefaultProductsTableBlock(id: string): ProductsTableBlock {
  return {
    id,
    type: 'products_table',
    source: 'offer_lines',
    width_pct: WIDTH_PCT_MAX,
    borders: null,
    show_header: true,
    header_background: null,
    columns: [],
    totals: { show: false, rows: [] },
    empty_text: '',
  }
}

export function createDefaultPageBreakBlock(id: string): PageBreakBlock {
  return { id, type: 'page_break' }
}

export function createDefaultSpacerBlock(id: string): SpacerBlock {
  return { id, type: 'spacer', height: DEFAULT_SPACER_HEIGHT }
}

export function createDefaultDividerBlock(id: string): DividerBlock {
  return {
    id,
    type: 'divider',
    width_pct: DEFAULT_DIVIDER_WIDTH_PCT,
    thickness: DEFAULT_DIVIDER_THICKNESS,
    color: DEFAULT_FONT_COLOR,
    space_before: DEFAULT_DIVIDER_SPACING_TWIPS,
    space_after: DEFAULT_DIVIDER_SPACING_TWIPS,
  }
}

/**
 * A fresh, unformatted run — the visual editor's (wave 2) "add run"/table
 * cell seed. `field` is `null` by default (a plain text run); pass a
 * `RunField` to seed a page-numbering run instead (AC-125), which ignores
 * `text` server-side.
 */
export function createDefaultRun(field: RunField | null = null): DocumentLayoutRun {
  return { text: '', field, bold: false, italic: false, underline: false, font: null, size: null, color: null }
}

export function createDefaultTableCell(): TableCell {
  return { col_span: 1, background: null, vertical_align: 'top', blocks: [] }
}

export function createDefaultTableRow(columnCount: number): TableRow {
  return { is_header: false, cells: Array.from({ length: columnCount }, () => createDefaultTableCell()) }
}

/** Width, in percent, of a newly added `products_table` column (editor default only, not a contract limit). */
const DEFAULT_PRODUCT_COLUMN_WIDTH_PCT = 20

export function createDefaultProductColumnLine(): ProductColumnLine {
  return { keys: [], separator: '', bold: false, italic: false, size: null }
}

export function createDefaultProductColumn(): ProductColumn {
  return {
    lines: [createDefaultProductColumnLine()],
    label: '',
    width_pct: DEFAULT_PRODUCT_COLUMN_WIDTH_PCT,
    align: 'left',
  }
}

export function createDefaultTotalsRow(): ProductsTableTotalsRow {
  return { label: '', variable: '', bold: false }
}

/** Block types with a uniform, argument-less default factory (everything but `image`). */
export type DefaultableBlockType = Exclude<BlockType, 'image'>

export const DEFAULTABLE_BLOCK_TYPES: readonly DefaultableBlockType[] = BLOCK_TYPES.filter(
  (type): type is DefaultableBlockType => type !== 'image',
)

/**
 * Builds a new block of `type` with its default values, keyed by `id`
 * (client-generated, spec 0069 `config_schema`). Used by the editor's
 * "add block" action (wave 2, AC-120). `image` is not representable: it
 * always needs a concrete `attachment_id`, see `createDefaultImageBlock`.
 */
export function createDefaultBlock(type: DefaultableBlockType, id: string): Block {
  switch (type) {
    case 'text':
      return createDefaultTextBlock(id)
    case 'table':
      return createDefaultTableBlock(id)
    case 'products_table':
      return createDefaultProductsTableBlock(id)
    case 'page_break':
      return createDefaultPageBreakBlock(id)
    case 'spacer':
      return createDefaultSpacerBlock(id)
    case 'divider':
      return createDefaultDividerBlock(id)
  }
}
