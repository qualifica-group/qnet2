import { DateTimeCell } from '@/features/table/cell-renderers'
import { CodeBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`, built from the
 * shared cross-module cell library. `name`/`description` fall back
 * to the AG Grid default cells; `code` renders as a compact monospace badge
 * (mirrors `paymentMethodColumnRenderers`); `created_at`/`updated_at` reuse
 * the shared datetime renderer.
 */
export const productTypologyColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
