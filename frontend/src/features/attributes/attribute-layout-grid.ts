import type { LayoutColumns, LayoutItemWidth } from '@/features/attributes/attribute-layout-types'

/**
 * Static Tailwind class lookup for the layout grid (spec 0062 `constraints`:
 * "classi Tailwind STATICHE, mai stringhe dinamiche" — the JIT scanner only
 * picks up class names that appear literally in source, so every value below
 * is a fully-written string, never built with template interpolation).
 *
 * Responsive collapse (spec D4/AC-013), keyed to the CONTAINER width via
 * container queries (`@`-variants), NOT the viewport: this layout renders
 * inside variable-width panels (product form/detail sheet, configurator
 * preview), so a wide window with a narrow panel must still collapse. The
 * consuming section marks its content as an `@container`. Collapse stays three
 * tiers: base is always 1 column; `@xs` (~20rem container) reaches
 * `min(columns, 2)`; the full tier reaches the section's configured `columns`
 * — `@md` (~28rem/448px) for 3, `@lg` (~32rem/512px) for 4. Thresholds are
 * deliberately COMPACT (≈150px/column, ui-design.md §2) so the configured
 * column count actually appears at real panel widths, not only on a maximized
 * window. A breakpoint prefix is only emitted when its value differs from the
 * previous tier (the `@`-cascade keeps the earlier value otherwise), which is
 * why the tables below are not a uniform formula string. 3 and 4 columns share
 * the `@md` (448px) full tier: an admin who picks 4 columns wants 4 even in a
 * ~450px panel (≈112px/column), the density the compact scale accepts.
 *
 * `item.width` is a fraction of `columns`, resolved with the frozen formula
 * (`layout-contract` semantics): full=columns, two_thirds=ceil(2*columns/3),
 * half=ceil(columns/2), third=ceil(columns/3), quarter=ceil(columns/4) — each
 * column-span computed at every tier's effective column count, then clamped to
 * that count. `quarter` is the only width that stays a single cell in a 4-wide
 * grid (the others bottom out at 2), so it is what tiles 4 items across a row.
 */

const GRID_COLS_CLASS: Record<LayoutColumns, string> = {
  1: 'grid-cols-1',
  2: 'grid-cols-1 @xs:grid-cols-2',
  3: 'grid-cols-1 @xs:grid-cols-2 @md:grid-cols-3',
  4: 'grid-cols-1 @xs:grid-cols-2 @md:grid-cols-4',
}

const ITEM_SPAN_CLASS: Record<LayoutColumns, Record<LayoutItemWidth, string>> = {
  1: {
    full: 'col-span-1',
    two_thirds: 'col-span-1',
    half: 'col-span-1',
    third: 'col-span-1',
    quarter: 'col-span-1',
  },
  2: {
    full: 'col-span-1 @xs:col-span-2',
    two_thirds: 'col-span-1 @xs:col-span-2',
    half: 'col-span-1',
    third: 'col-span-1',
    quarter: 'col-span-1',
  },
  3: {
    full: 'col-span-1 @xs:col-span-2 @md:col-span-3',
    two_thirds: 'col-span-1 @xs:col-span-2',
    half: 'col-span-1 @md:col-span-2',
    third: 'col-span-1',
    quarter: 'col-span-1',
  },
  4: {
    full: 'col-span-1 @xs:col-span-2 @md:col-span-4',
    two_thirds: 'col-span-1 @xs:col-span-2 @md:col-span-3',
    half: 'col-span-1 @md:col-span-2',
    third: 'col-span-1 @md:col-span-2',
    quarter: 'col-span-1',
  },
}

/** Compact default gap for the layout grid (ui-design.md §2 sizing scale). */
export const LAYOUT_GRID_GAP_CLASS = 'gap-3'

/** The section's base `grid-cols-*` class, collapsing to 1 column on mobile. */
export function gridColsClass(columns: LayoutColumns): string {
  return GRID_COLS_CLASS[columns]
}

/** One item's `col-span-*` class inside a `columns`-wide grid, for its configured `width`. */
export function itemSpanClass(columns: LayoutColumns, width: LayoutItemWidth): string {
  return ITEM_SPAN_CLASS[columns][width]
}
