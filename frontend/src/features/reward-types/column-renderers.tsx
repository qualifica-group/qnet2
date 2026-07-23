import { DateTimeCell } from '@/features/table/cell-renderers'
import { ColorSwatchCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the
 * shared cross-module cell library so the grid matches the other anagraphic
 * configurators. `name` falls back to the AG Grid default cell; `color`
 * renders a swatch dot + localized token name; `created_at`/`updated_at`
 * reuse the shared datetime renderer. No `GroupCell`: this module has no
 * `group` field (spec 0058, D-1).
 */
export const rewardTypeColumnRenderers: TableRendererMap = {
  color: (params) => <ColorSwatchCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
