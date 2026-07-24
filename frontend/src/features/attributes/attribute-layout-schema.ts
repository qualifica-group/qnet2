import { z } from 'zod'
import { LAYOUT_ITEM_WIDTHS, LAYOUT_SECTION_VARIANTS } from '@/features/attributes/attribute-layout-types'

/**
 * Zod schema for the layout blob (spec 0062 `layout-contract`, frozen shape):
 * single source of truth for the attribute-layout configurator's form state
 * and for validating a blob before `PUT`. Mirrors
 * `attribute-layout-types.ts`'s hand-written TS types 1:1 — kept as a
 * separate file (rather than `z.infer`-deriving the types) so the plain
 * contract types stay free of a Zod dependency, matching this codebase's
 * existing type/schema split (`custom-fields/types.ts` vs
 * `*-definition-schema.ts`).
 */

const TITLE_MAX_LENGTH = 191
const DESCRIPTION_MAX_LENGTH = 500

export const layoutSectionVariantSchema = z.enum(
  LAYOUT_SECTION_VARIANTS as [string, ...string[]],
)
export const layoutItemWidthSchema = z.enum(LAYOUT_ITEM_WIDTHS as [string, ...string[]])
export const layoutColumnsSchema = z.union([z.literal(1), z.literal(2), z.literal(3), z.literal(4)])

export const layoutItemSchema = z.object({
  attribute_code: z.string().min(1),
  width: layoutItemWidthSchema,
})

export const layoutRowSchema = z.object({
  id: z.string().min(1),
  items: z.array(layoutItemSchema),
})

export const layoutSectionSchema = z.object({
  id: z.string().min(1),
  title: z.string().min(1).max(TITLE_MAX_LENGTH),
  description: z.string().max(DESCRIPTION_MAX_LENGTH).nullable(),
  variant: layoutSectionVariantSchema,
  collapsible: z.boolean(),
  default_collapsed: z.boolean(),
  is_advanced: z.boolean(),
  columns: layoutColumnsSchema,
  sort_order: z.number().int().nonnegative(),
  rows: z.array(layoutRowSchema),
})

/** Every `attribute_code` in the blob must appear at most once (spec `layout-contract` semantics). */
function assertNoDuplicateCodes(blob: { sections: z.infer<typeof layoutSectionSchema>[] }, ctx: z.RefinementCtx): void {
  const seen = new Set<string>()
  blob.sections.forEach((section, sectionIndex) => {
    section.rows.forEach((row, rowIndex) => {
      row.items.forEach((item, itemIndex) => {
        if (seen.has(item.attribute_code)) {
          ctx.addIssue({
            code: 'custom',
            path: ['sections', sectionIndex, 'rows', rowIndex, 'items', itemIndex, 'attribute_code'],
            message: `Duplicate attribute_code: ${item.attribute_code}`,
          })
          return
        }
        seen.add(item.attribute_code)
      })
    })
  })
}

export const attributeLayoutBlobSchema = z
  .object({ sections: z.array(layoutSectionSchema) })
  .superRefine(assertNoDuplicateCodes)

export type AttributeLayoutBlobInput = z.infer<typeof attributeLayoutBlobSchema>
