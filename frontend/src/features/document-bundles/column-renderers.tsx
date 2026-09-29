import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`. `name`/
 * `files_count` fall back to the AG Grid default cells; `is_active` renders
 * a colored yes/no badge; `updated_at` reuses the shared datetime renderer.
 */
export const documentBundleColumnRenderers: TableRendererMap = {
  is_active: (params) => <BooleanBadgeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
