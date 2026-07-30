/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import i18n from '@/i18n'
import type { ICellRendererParams } from 'ag-grid-community'
import { DateTimeCell, EmptyCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell, CodeBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Renders the raw `module` enum value (e.g. `"quotes"`) as its localized
 * label (`documentLayouts.modules.<value>`). Unlike a backend `badge` column
 * (`enumKey`, spec 0069 table contract declares `module` as plain
 * `filterType: text`), the row carries only the raw value, so the mapping to
 * copy happens here, client-side.
 */
function ModuleCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell align="left" />
  }
  return <span className="truncate">{i18n.t(`documentLayouts.modules.${value}`)}</span>
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0069
 * `GET /tables/document-layouts/columns`), built from the shared cross-module
 * cell library (mirrors `paymentMethodColumnRenderers`). `name`/`description`
 * fall back to the AG Grid default cell; `code` renders as a compact
 * monospace badge; `is_active`/`is_default` render a colored yes/no badge
 * (`is_default` stays display-only in the grid: it is NOT inline-editable,
 * spec D-7); `created_at`/`updated_at` reuse the shared datetime renderer.
 */
export const documentLayoutColumnRenderers: TableRendererMap = {
  code: (params) => <CodeBadgeCell {...params} />,
  module: (params) => <ModuleCell {...params} />,
  is_active: (params) => <BooleanBadgeCell {...params} />,
  is_default: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
