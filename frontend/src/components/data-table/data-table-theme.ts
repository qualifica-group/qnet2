import { iconOverrides, themeQuartz } from 'ag-grid-community'

/**
 * Funnel glyph for the column header filter button. The stock Quartz `filter`
 * icon is three stacked lines, not a funnel; this replaces it with a funnel
 * outline in the app's lucide icon language. The stroke color is irrelevant —
 * `mask: true` uses only the shape's alpha, so the button keeps the theme's
 * icon color (and tracks dark mode) automatically.
 */
const FILTER_FUNNEL_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>'

/**
 * Compact base sizing (pixels) at 100% UI scale. AG Grid sizes are absolute
 * pixels, so unlike the rem-based rest of the app they do not follow the root
 * font-size — `buildDataTableTheme` multiplies them by the user's UI scale
 * factor (see features/appearance/ui-scale) to keep the grid in step.
 */
const BASE_FONT_SIZE = 12
const BASE_ROW_HEIGHT = 28
const BASE_HEADER_HEIGHT = 32
const BASE_HEADER_FONT_SIZE = 10
const BASE_CELL_HORIZONTAL_PADDING = 10

/**
 * AG Grid draws the pagination panel at `max(rowHeight, 22px)` (Quartz default
 * for `paginationPanelHeight`); the 22px floor is an absolute pixel value the
 * theme does not scale.
 */
const MIN_PAGINATION_PANEL_HEIGHT = 22

/**
 * Pixel height a grid needs to show `rowCount` rows without scrolling
 * internally: column header + rows + pagination panel, at the given UI scale.
 * Callers sizing a grid container use it as the ceiling, so a tall viewport
 * does not leave empty grid below the last row of the page.
 */
export function estimateGridHeight(rowCount: number, factor: number): number {
  const rowHeight = Math.round(BASE_ROW_HEIGHT * factor)
  const headerHeight = Math.round(BASE_HEADER_HEIGHT * factor)

  return (
    headerHeight +
    rowHeight * Math.max(rowCount, 0) +
    Math.max(rowHeight, MIN_PAGINATION_PANEL_HEIGHT)
  )
}

/**
 * Compact grid theme aligned to the app's design tokens, scaled by `factor` (the
 * per-user UI scale). The grid sits on the white `--card` surface so it stands
 * out against the grey `--background` body, while borders/hover/header text
 * reference the shared CSS variables (`--border`, `--muted*`) so it tracks the
 * app palette and dark mode automatically. Sizing is tightened (smaller rows,
 * smaller font) and cells/headers carry light borders in the app border color.
 */
export function buildDataTableTheme(factor: number) {
  return themeQuartz
    .withParams({
      fontFamily: 'inherit',
      fontSize: Math.round(BASE_FONT_SIZE * factor),
      rowHeight: Math.round(BASE_ROW_HEIGHT * factor),
      headerHeight: Math.round(BASE_HEADER_HEIGHT * factor),
      headerFontSize: Math.round(BASE_HEADER_FONT_SIZE * factor),
      headerFontWeight: 600,
      cellHorizontalPadding: Math.round(BASE_CELL_HORIZONTAL_PADDING * factor),
      backgroundColor: 'var(--card)',
      foregroundColor: 'var(--card-foreground)',
      // Softened hairline: the shared `--border` is tuned to edge cards and
      // panels, and repeating it on every cell of a 28px-row grid would draw a
      // spreadsheet. Mixing it back toward the grid's own surface keeps the
      // separators readable without turning the table into a mesh, and still
      // tracks the token (and dark mode) instead of hard-coding a colour. The
      // share is 40% (was 55%) because the hairline itself was darkened when
      // the surface ladder widened: the mesh keeps the same 1.3:1 weight.
      borderColor: 'color-mix(in srgb, var(--border) 40%, var(--card))',
      headerBackgroundColor: 'var(--card)',
      headerTextColor: 'var(--muted-foreground)',
      // Same reasoning for the hover wash: `--muted` sits beyond the end of the
      // ladder to be felt on any host, which on a dense grid would read as a
      // selected row. Mixed back toward the grid surface it stays a hover.
      rowHoverColor: 'color-mix(in srgb, var(--muted) 65%, var(--card))',
      // The grid is fused into the toolbar block (spec 0009): the outer card owns
      // the border and radius, so the grid drops its own wrapper border to read as
      // one continuous surface with the header above it.
      wrapperBorder: false,
      rowBorder: true,
      columnBorder: true,
      headerColumnBorder: true,
      wrapperBorderRadius: 0,
    })
    // Swap the header filter button's three-line glyph for an actual funnel.
    .withPart(
      iconOverrides({
        type: 'image',
        mask: true,
        icons: { filter: { svg: FILTER_FUNNEL_SVG } },
      }),
    )
}
