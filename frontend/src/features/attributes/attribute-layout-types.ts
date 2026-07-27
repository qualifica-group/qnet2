import type { CustomFieldValue } from '@/features/custom-fields/types'

/**
 * The frozen layout blob contract (spec 0062 `layout-contract`, backend
 * `App\Enums\LayoutSectionVariant` / `App\Enums\LayoutItemWidth`). Persisted
 * as-is in `attribute_layouts.layout`; the FE renderer/configurator consume
 * this shape verbatim — do not rename a field without updating the spec.
 */

/** Mirrors `ConfigSectionVariant` (`components/ui/config-section.tsx`) — kept as its own literal union here so this file has zero UI-layer import. */
export type LayoutSectionVariant = 'default' | 'highlighted' | 'informative' | 'secondary'

/** `item.width`: the fraction of the section's `columns` an item spans (see `attribute-layout-grid.ts`). */
export type LayoutItemWidth = 'full' | 'two_thirds' | 'half' | 'third' | 'quarter'

/** `section.columns`: the desktop grid-cols count a section's rows lay items into. */
export type LayoutColumns = 1 | 2 | 3 | 4

export const LAYOUT_SECTION_VARIANTS: readonly LayoutSectionVariant[] = [
  'default',
  'highlighted',
  'informative',
  'secondary',
]

export const LAYOUT_ITEM_WIDTHS: readonly LayoutItemWidth[] = ['full', 'two_thirds', 'half', 'third', 'quarter']

export const LAYOUT_COLUMNS_OPTIONS: readonly LayoutColumns[] = [1, 2, 3, 4]

/** One attribute placed in a row, at a given fractional width. */
export interface LayoutItem {
  attribute_code: string
  width: LayoutItemWidth
}

/** A manual line break inside a section: every row is a new CSS grid row. */
export interface LayoutRow {
  id: string
  items: LayoutItem[]
}

/** A titled, optionally collapsible group of rows. */
export interface LayoutSection {
  id: string
  title: string
  description: string | null
  variant: LayoutSectionVariant
  collapsible: boolean
  default_collapsed: boolean
  columns: LayoutColumns
  sort_order: number
  rows: LayoutRow[]
}

/** The full persisted blob for one (product_category, context, form_mode). */
export interface LayoutBlob {
  sections: LayoutSection[]
}

/** Mirrors backend `App\Enums\FormMode`: the lifecycle stage a record is actually rendered in. */
export type LayoutFormMode = 'create' | 'edit' | 'view'

/**
 * Mirrors backend `App\Enums\LayoutFormScope`: which form modes a CONFIGURED
 * layout applies to — `'all'` is the shared layout driving every mode, the
 * other three are per-mode overrides of it. Only the configurator speaks this
 * type; consumers (product form/detail) always request a `LayoutFormMode`.
 */
export type LayoutFormScope = 'all' | LayoutFormMode

/**
 * Shape a host form must extend to mount `AttributeLayoutRenderer`: values
 * keyed by attribute `code` (mirrors `products.attribute_values` /
 * `opportunities.attribute_values`, spec 0061 — NOT the `custom_fields`
 * namespaced shape of `CustomFieldsFormShape`).
 */
export interface AttributeLayoutFormShape {
  attribute_values: Record<string, CustomFieldValue>
}
