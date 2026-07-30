import type { PageOrientation } from '@/features/document-layouts/layout-config'

/** OOXML default A4 page size, in twips — the same unit as `config.page.margins` (spec 0069 `config_schema`). */
export const PAGE_WIDTH_TWIPS = 11906
export const PAGE_HEIGHT_TWIPS = 16838

/** 1 point = 20 twips (OOXML unit fact, not a second free conversion constant). */
const TWIPS_PER_POINT = 20

/**
 * SINGLE twips->px conversion constant for the client-side A4 preview (spec
 * 0069 D-8/AC-127): previewed at 1 px per point (72 dpi) — the same point
 * unit already used for font/image sizes elsewhere in the config, so a 12pt
 * run and a 240-twip space read at a consistent scale. Every dimension the
 * preview renders (page size, margins, image points) derives from this one
 * constant — `pointsToPx` is not a second scale, just twips expressed via
 * the 20:1 point ratio.
 */
export const PREVIEW_PX_PER_TWIP = 1 / 20

export function twipsToPx(twips: number): number {
  return twips * PREVIEW_PX_PER_TWIP
}

export function pointsToPx(points: number): number {
  return twipsToPx(points * TWIPS_PER_POINT)
}

/** A4 sheet size in px for the preview, swapped for `landscape` (AC-127: "il foglio ha il rapporto d'aspetto A4"). */
export function pageSizePx(orientation: PageOrientation): { width: number; height: number } {
  const width = twipsToPx(PAGE_WIDTH_TWIPS)
  const height = twipsToPx(PAGE_HEIGHT_TWIPS)
  return orientation === 'landscape' ? { width: height, height: width } : { width, height }
}
