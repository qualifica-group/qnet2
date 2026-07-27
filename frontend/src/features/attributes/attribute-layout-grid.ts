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
 * tiers: base is always 1 column; `@md` (~28rem container) reaches
 * `min(columns, 2)`; the wide tier reaches the section's configured `columns`
 * — `@3xl` (~48rem) for 3, `@5xl` (~64rem) for 4, each sized so every column
 * keeps a usable min width before it appears. A breakpoint prefix is only
 * emitted when its value differs from the previous tier (the `@`-cascade keeps
 * the earlier value otherwise), which is why the tables below are not a
 * uniform formula string.
 *
 * `item.width` is a fraction of `columns`, resolved with the frozen formula
 * (`layout-contract` semantics): full=columns, two_thirds=ceil(2*columns/3),
 * half=ceil(columns/2), third=ceil(columns/3) — each column-span computed at
 * every tier's effective column count, then clamped to that count.
 */

const GRID_COLS_CLASS: Record<LayoutColumns, string> = {
  1: 'grid-cols-1',
  2: 'grid-cols-1 @md:grid-cols-2',
  3: 'grid-cols-1 @md:grid-cols-2 @3xl:grid-cols-3',
  4: 'grid-cols-1 @md:grid-cols-2 @5xl:grid-cols-4',
}

const ITEM_SPAN_CLASS: Record<LayoutColumns, Record<LayoutItemWidth, string>> = {
  1: {
    full: 'col-span-1',
    two_thirds: 'col-span-1',
    half: 'col-span-1',
    third: 'col-span-1',
  },
  2: {
    full: 'col-span-1 @md:col-span-2',
    two_thirds: 'col-span-1 @md:col-span-2',
    half: 'col-span-1',
    third: 'col-span-1',
  },
  3: {
    full: 'col-span-1 @md:col-span-2 @3xl:col-span-3',
    two_thirds: 'col-span-1 @md:col-span-2',
    half: 'col-span-1 @3xl:col-span-2',
    third: 'col-span-1',
  },
  4: {
    full: 'col-span-1 @md:col-span-2 @5xl:col-span-4',
    two_thirds: 'col-span-1 @md:col-span-2 @5xl:col-span-3',
    half: 'col-span-1 @5xl:col-span-2',
    third: 'col-span-1 @5xl:col-span-2',
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
