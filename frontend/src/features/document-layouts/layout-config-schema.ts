import { z } from 'zod'
import {
  ALIGN_LCR,
  CELL_VERTICAL_ALIGNS,
  COLUMN_KEYS,
  CONFIG_VERSION,
  IMAGE_WRAPS,
  PAGE_ORIENTATIONS,
  PRODUCTS_TABLE_SOURCES,
  RUN_FIELDS,
  TEXT_ALIGNS,
  type DocumentLayoutZoneName,
} from '@/features/document-layouts/layout-config'
import {
  DIVIDER_THICKNESS_MAX,
  DIVIDER_THICKNESS_MIN,
  FONT_SIZE_MAX,
  FONT_SIZE_MIN,
  LINE_HEIGHT_MAX,
  LINE_HEIGHT_MIN,
  MARGIN_TWIPS_MAX,
  MARGIN_TWIPS_MIN,
  MAX_BLOCKS_PER_ZONE,
  MAX_CONFIG_BYTES,
  MAX_IMAGE_POINTS,
  MAX_KEYS_PER_LINE,
  MAX_LINES_PER_PRODUCT_COLUMN,
  MAX_PRODUCT_COLUMNS,
  MAX_RUN_CHARS,
  MAX_RUNS_PER_BLOCK,
  MAX_TABLE_COLUMNS,
  MAX_TABLE_ROWS,
  MAX_TOTALS_ROWS,
  SPACER_HEIGHT_MAX,
  SPACER_HEIGHT_MIN,
  WIDTH_PCT_MAX,
  WIDTH_PCT_MIN,
} from '@/features/document-layouts/layout-config-defaults'

/**
 * Structural Zod validation of the `document_layouts.config` contract (spec
 * 0069 `config_schema`/AC-030..AC-039b). Mirrors the backend
 * `DocumentLayoutConfigValidator`'s allow-list-of-shapes approach client-side
 * so the editor (wave 2) can reject an invalid config before it ever reaches
 * the server, using the exact same limit constants. Kept separate from
 * `layout-config.ts`'s hand-written TS types (same split as
 * `attribute-layout-types.ts`/`attribute-layout-schema.ts`): plain messages,
 * not i18next — this is a structural contract check, not a per-request user
 * message (the editor maps a path-based 422 onto the UI itself, AC-129).
 *
 * Deliberately NOT covered here (cross-record / catalog-dependent, server
 * only): `image.attachment_id` ownership, `products_table.totals.rows[].variable`
 * membership in the live variable catalog, `custom_fields`/`opportunity_attributes`
 * existence. Those need data this module has no access to.
 */

const hexColorSchema = z.string().regex(/^[0-9A-Fa-f]{6}$/, 'Color must be a 6-digit hex RRGGBB value without #.')
const fontSizeSchema = z.number().int().min(FONT_SIZE_MIN).max(FONT_SIZE_MAX)
const percentSchema = z.number().int().min(WIDTH_PCT_MIN).max(WIDTH_PCT_MAX)
const nonNegativeIntSchema = z.number().int().nonnegative()

const textAlignSchema = z.enum(TEXT_ALIGNS)
const alignLcrSchema = z.enum(ALIGN_LCR)
const columnKeySchema = z.enum(COLUMN_KEYS)

const runSchema = z
  .object({
    text: z.string().max(MAX_RUN_CHARS),
    field: z.enum(RUN_FIELDS).nullable(),
    bold: z.boolean(),
    italic: z.boolean(),
    underline: z.boolean(),
    font: z.string().nullable(),
    size: fontSizeSchema.nullable(),
    color: hexColorSchema.nullable(),
  })
  .strict()

export const textBlockSchema = z
  .object({
    id: z.string().min(1),
    type: z.literal('text'),
    align: textAlignSchema,
    space_before: nonNegativeIntSchema,
    space_after: nonNegativeIntSchema,
    line_height: z.number().min(LINE_HEIGHT_MIN).max(LINE_HEIGHT_MAX),
    runs: z.array(runSchema).max(MAX_RUNS_PER_BLOCK),
  })
  .strict()

/** Field-level image rules only; the `behind_page`-outside-`header` rule is enforced at the zone level. */
export const imageBlockSchema = z
  .object({
    id: z.string().min(1),
    type: z.literal('image'),
    attachment_id: z.number().int().positive(),
    width: z.number().int().positive().max(MAX_IMAGE_POINTS),
    height: z.number().int().positive().max(MAX_IMAGE_POINTS).nullable(),
    align: alignLcrSchema,
    wrap: z.enum(IMAGE_WRAPS),
  })
  .strict()
  .superRefine((image, ctx) => {
    if (image.wrap === 'behind_page' && image.height === null) {
      ctx.addIssue({ code: 'custom', path: ['height'], message: 'height is required when wrap is behind_page.' })
    }
  })

const tableBordersSchema = z.object({ size: nonNegativeIntSchema, color: hexColorSchema }).strict()
const tableColumnSchema = z.object({ width_pct: percentSchema }).strict()

const tableCellSchema = z
  .object({
    col_span: z.number().int().positive(),
    background: hexColorSchema.nullable(),
    vertical_align: z.enum(CELL_VERTICAL_ALIGNS),
    blocks: z.array(textBlockSchema),
  })
  .strict()

const tableRowSchema = z
  .object({ is_header: z.boolean(), cells: z.array(tableCellSchema).max(MAX_TABLE_COLUMNS) })
  .strict()

export const tableBlockSchema = z
  .object({
    id: z.string().min(1),
    type: z.literal('table'),
    width_pct: percentSchema,
    borders: tableBordersSchema.nullable(),
    columns: z.array(tableColumnSchema).max(MAX_TABLE_COLUMNS),
    rows: z.array(tableRowSchema).max(MAX_TABLE_ROWS),
  })
  .strict()

const productColumnLineSchema = z
  .object({
    keys: z.array(columnKeySchema).min(1).max(MAX_KEYS_PER_LINE),
    separator: z.string(),
    bold: z.boolean(),
    italic: z.boolean(),
    size: fontSizeSchema.nullable(),
  })
  .strict()

const productColumnSchema = z
  .object({
    lines: z.array(productColumnLineSchema).min(1).max(MAX_LINES_PER_PRODUCT_COLUMN),
    label: z.string(),
    width_pct: percentSchema,
    align: alignLcrSchema,
  })
  .strict()

const productsTableTotalsRowSchema = z
  .object({ label: z.string(), variable: z.string().min(1), bold: z.boolean() })
  .strict()

const productsTableTotalsSchema = z
  .object({ show: z.boolean(), rows: z.array(productsTableTotalsRowSchema).max(MAX_TOTALS_ROWS) })
  .strict()

export const productsTableBlockSchema = z
  .object({
    id: z.string().min(1),
    type: z.literal('products_table'),
    source: z.enum(PRODUCTS_TABLE_SOURCES),
    width_pct: percentSchema,
    borders: tableBordersSchema.nullable(),
    show_header: z.boolean(),
    header_background: hexColorSchema.nullable(),
    columns: z.array(productColumnSchema).max(MAX_PRODUCT_COLUMNS),
    totals: productsTableTotalsSchema,
    empty_text: z.string(),
  })
  .strict()

export const pageBreakBlockSchema = z.object({ id: z.string().min(1), type: z.literal('page_break') }).strict()

export const spacerBlockSchema = z
  .object({
    id: z.string().min(1),
    type: z.literal('spacer'),
    height: z.number().int().min(SPACER_HEIGHT_MIN).max(SPACER_HEIGHT_MAX),
  })
  .strict()

export const dividerBlockSchema = z
  .object({
    id: z.string().min(1),
    type: z.literal('divider'),
    width_pct: percentSchema,
    thickness: z.number().int().min(DIVIDER_THICKNESS_MIN).max(DIVIDER_THICKNESS_MAX),
    color: hexColorSchema,
    space_before: nonNegativeIntSchema,
    space_after: nonNegativeIntSchema,
  })
  .strict()

/** Discriminated union of the seven block shapes — an unknown `type` fails here (AC-030). */
export const blockSchema = z.discriminatedUnion('type', [
  textBlockSchema,
  imageBlockSchema,
  tableBlockSchema,
  productsTableBlockSchema,
  pageBreakBlockSchema,
  spacerBlockSchema,
  dividerBlockSchema,
])

const pageMarginsSchema = z
  .object({
    top: z.number().int().min(MARGIN_TWIPS_MIN).max(MARGIN_TWIPS_MAX),
    right: z.number().int().min(MARGIN_TWIPS_MIN).max(MARGIN_TWIPS_MAX),
    bottom: z.number().int().min(MARGIN_TWIPS_MIN).max(MARGIN_TWIPS_MAX),
    left: z.number().int().min(MARGIN_TWIPS_MIN).max(MARGIN_TWIPS_MAX),
  })
  .strict()

const pageDefaultFontSchema = z
  .object({ family: z.string().min(1), size: fontSizeSchema, color: hexColorSchema })
  .strict()

export const pageSchema = z
  .object({
    format: z.literal('A4'),
    orientation: z.enum(PAGE_ORIENTATIONS),
    margins: pageMarginsSchema,
    default_font: pageDefaultFontSchema,
  })
  .strict()

/**
 * `wrap: 'behind_page'` (D-11) is only meaningful in `header` (spec 0069
 * `config_schema`): in `body`/`footer` it would not repeat per page and would
 * overlap the content — the backend rejects it there, mirrored here.
 */
function zoneSchema(zoneName: DocumentLayoutZoneName) {
  return z
    .object({ blocks: z.array(blockSchema).max(MAX_BLOCKS_PER_ZONE) })
    .strict()
    .superRefine((zone, ctx) => {
      if (zoneName === 'header') {
        return
      }
      zone.blocks.forEach((block, index) => {
        if (block.type === 'image' && block.wrap === 'behind_page') {
          ctx.addIssue({
            code: 'custom',
            path: ['blocks', index, 'wrap'],
            message: `wrap "behind_page" is only allowed in the header zone, not in "${zoneName}".`,
          })
        }
      })
    })
}

/** Root config schema (spec 0069 `config_schema`), including the 256 KB serialized-size cap. */
export const documentLayoutConfigSchema = z
  .object({
    version: z.literal(CONFIG_VERSION),
    page: pageSchema,
    header: zoneSchema('header'),
    body: zoneSchema('body'),
    footer: zoneSchema('footer'),
  })
  .strict()
  .superRefine((config, ctx) => {
    const size = new TextEncoder().encode(JSON.stringify(config)).length
    if (size > MAX_CONFIG_BYTES) {
      ctx.addIssue({
        code: 'custom',
        path: [],
        message: `Serialized config is ${size} bytes, over the ${MAX_CONFIG_BYTES} byte limit.`,
      })
    }
  })

export type DocumentLayoutConfigInput = z.infer<typeof documentLayoutConfigSchema>
