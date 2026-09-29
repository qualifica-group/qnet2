import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { DateTimeCell, EmptyCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Custom cell renderers keyed by the backend column `id`. `name`/`subject`
 * fall back to the AG Grid default text cell; `module` renders its localized
 * label (via the shared `i18n` instance directly, same as `BooleanBadgeCell`,
 * so this stays a plain function rather than a hook-using component); `is_active`
 * renders a colored yes/no badge; `updated_at` reuses the shared datetime
 * renderer.
 */
export const emailTemplateColumnRenderers: TableRendererMap = {
  module: ({ value }: ICellRendererParams) => {
    if (typeof value !== 'string' || value === '') {
      return <EmptyCell align="left" />
    }
    return <span className="truncate">{i18n.t(`emailTemplates.modules.${value}`)}</span>
  },
  is_active: (params) => <BooleanBadgeCell {...params} />,
  updated_at: (params) => <DateTimeCell {...params} />,
}
