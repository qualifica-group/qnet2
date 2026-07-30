/**
 * Frozen TS contract of the `document_layouts.config` JSON column (spec 0069
 * `config_schema`). This is the shared surface between the visual editor
 * (wave 2), the frontend Zod validator (`layout-config-schema.ts`) and the
 * backend `DocumentLayoutConfigValidator` — every key here is implemented by
 * both sides, no key outside this file is a valid config field. Dimensional
 * limits and per-block-type default factories live in the sibling
 * `layout-config-defaults.ts` (split from this file to stay within the
 * engineering.md §6 size budget: this file is the pure type contract).
 *
 * UNITS (declared once, spec 0069 `config_schema`):
 *  - page margins: TWIPS (1/1440 inch) — same unit as `.docx`/PhpWord.
 *  - font sizes, block heights, image dimensions: POINTS (integers).
 *  - paragraph spacing (`space_before`/`space_after`): TWIPS.
 *  - column/table widths: INTEGER PERCENTAGE of the usable width.
 *  - colors: hex RRGGBB WITHOUT `#` (OOXML convention).
 *
 * NAMING NOTE (binding, spec 0069 `config_schema`): the per-row token key is
 * `variable`, never `token` — the repo's `secret-scan.sh` hook flags any
 * `token` key assigned a literal value as a possible leaked secret.
 */

/** The only supported `config.version` value today. */
export const CONFIG_VERSION = 1

// ---------------------------------------------------------------------------
// Zones, block types and shared enums.
// ---------------------------------------------------------------------------

export const DOCUMENT_LAYOUT_ZONES = ['header', 'body', 'footer'] as const
export type DocumentLayoutZoneName = (typeof DOCUMENT_LAYOUT_ZONES)[number]

export const BLOCK_TYPES = [
  'text',
  'image',
  'table',
  'products_table',
  'page_break',
  'spacer',
  'divider',
] as const
export type BlockType = (typeof BLOCK_TYPES)[number]

/** `text.align` / table cell paragraph alignment. */
export const TEXT_ALIGNS = ['left', 'center', 'right', 'justify'] as const
export type TextAlign = (typeof TEXT_ALIGNS)[number]

/** `image.align` and `products_table.columns[].align`: no `justify`. */
export const ALIGN_LCR = ['left', 'center', 'right'] as const
export type AlignLCR = (typeof ALIGN_LCR)[number]

/** `image.wrap`: `inline` flows with the content, `behind_page` is a full-page background (header only, D-11). */
export const IMAGE_WRAPS = ['inline', 'behind_page'] as const
export type ImageWrap = (typeof IMAGE_WRAPS)[number]

/** `runs[].field`: a run with a field set renders a computed Word field instead of its `text`. */
export const RUN_FIELDS = ['page', 'total_pages'] as const
export type RunField = (typeof RUN_FIELDS)[number]

export const CELL_VERTICAL_ALIGNS = ['top', 'center', 'bottom'] as const
export type CellVerticalAlign = (typeof CELL_VERTICAL_ALIGNS)[number]

/** `products_table.source`: which set of quote lines feeds the table. */
export const PRODUCTS_TABLE_SOURCES = ['offer_lines', 'cost_lines'] as const
export type ProductsTableSource = (typeof PRODUCTS_TABLE_SOURCES)[number]

export const PAGE_ORIENTATIONS = ['portrait', 'landscape'] as const
export type PageOrientation = (typeof PAGE_ORIENTATIONS)[number]

/** `products_table.columns[].lines[].keys`: closed allow-list, `discount` deliberately absent (D-4). */
export const COLUMN_KEYS = [
  'code',
  'name',
  'description',
  'quantity',
  'unit_price',
  'vat_rate',
  'net_amount',
  'vat_amount',
  'total_amount',
] as const
export type ColumnKey = (typeof COLUMN_KEYS)[number]

// ---------------------------------------------------------------------------
// Block shapes.
// ---------------------------------------------------------------------------

/** One formatted run inside a `text` block or a `table` cell paragraph. */
export interface DocumentLayoutRun {
  text: string
  /** When set, `text` is ignored and a computed Word field is rendered instead. */
  field: RunField | null
  bold: boolean
  italic: boolean
  underline: boolean
  font: string | null
  size: number | null
  color: string | null
}

/** 1) TEXT — the base block, a paragraph of formatted runs. */
export interface TextBlock {
  id: string
  type: 'text'
  align: TextAlign
  space_before: number
  space_after: number
  line_height: number
  runs: DocumentLayoutRun[]
}

/** 2) IMAGE — a logo or header image uploaded to the layout. */
export interface ImageBlock {
  id: string
  type: 'image'
  /** Must belong to this layout (`attachable` = layout, `collection` = `layout_image`); enforced server-side. */
  attachment_id: number
  width: number
  /** Points; `null` = proportional to `width`. Required (non-null) when `wrap === 'behind_page'`. */
  height: number | null
  align: AlignLCR
  wrap: ImageWrap
}

export interface TableBorders {
  /** Eighths of a point. */
  size: number
  color: string
}

export interface TableColumn {
  width_pct: number
}

/** A `table` cell may only contain `text` blocks — no nested tables, no images (spec 0069). */
export interface TableCell {
  col_span: number
  background: string | null
  vertical_align: CellVerticalAlign
  blocks: TextBlock[]
}

export interface TableRow {
  is_header: boolean
  cells: TableCell[]
}

/** 3) TABLE — a static table. */
export interface TableBlock {
  id: string
  type: 'table'
  width_pct: number
  borders: TableBorders | null
  columns: TableColumn[]
  rows: TableRow[]
}

/** One stacked paragraph inside a `products_table` column cell. */
export interface ProductColumnLine {
  keys: ColumnKey[]
  separator: string
  bold: boolean
  italic: boolean
  size: number | null
}

/** One column of the dynamic products table; its cell content is composed from `lines`. */
export interface ProductColumn {
  lines: ProductColumnLine[]
  /** Free-text, user-localized column header — not a variable token. */
  label: string
  width_pct: number
  align: AlignLCR
}

export interface ProductsTableTotalsRow {
  label: string
  /** Must be a `totals.*` variable (validated against the variable catalog server-side). */
  variable: string
  bold: boolean
}

export interface ProductsTableTotals {
  show: boolean
  rows: ProductsTableTotalsRow[]
}

/** 4) PRODUCTS_TABLE — the dynamic table of the source record's product lines. */
export interface ProductsTableBlock {
  id: string
  type: 'products_table'
  source: ProductsTableSource
  width_pct: number
  borders: TableBorders | null
  show_header: boolean
  header_background: string | null
  columns: ProductColumn[]
  totals: ProductsTableTotals
  empty_text: string
}

/** 5) PAGE_BREAK — forces a new page. */
export interface PageBreakBlock {
  id: string
  type: 'page_break'
}

/** 6) SPACER — vertical blank space. */
export interface SpacerBlock {
  id: string
  type: 'spacer'
  /** Points, 1..200. */
  height: number
}

/** 7) DIVIDER — horizontal separator rule (D-11), rendered as a bottom-bordered empty paragraph. */
export interface DividerBlock {
  id: string
  type: 'divider'
  width_pct: number
  /** Eighths of a point. */
  thickness: number
  color: string
  space_before: number
  space_after: number
}

/** Discriminated union of the seven block types, keyed on `type`. */
export type Block =
  | TextBlock
  | ImageBlock
  | TableBlock
  | ProductsTableBlock
  | PageBreakBlock
  | SpacerBlock
  | DividerBlock

// ---------------------------------------------------------------------------
// Page and root config shapes.
// ---------------------------------------------------------------------------

export interface PageMargins {
  top: number
  right: number
  bottom: number
  left: number
}

export interface PageDefaultFont {
  family: string
  size: number
  color: string
}

export interface DocumentLayoutPage {
  format: 'A4'
  orientation: PageOrientation
  margins: PageMargins
  default_font: PageDefaultFont
}

export interface DocumentLayoutZone {
  blocks: Block[]
}

/** The full `document_layouts.config` shape (spec 0069 `config_schema`). */
export interface DocumentLayoutConfig {
  version: 1
  page: DocumentLayoutPage
  header: DocumentLayoutZone
  body: DocumentLayoutZone
  footer: DocumentLayoutZone
}
