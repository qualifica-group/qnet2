/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { DateTimeCell, EmptyCell } from '@/features/table/cell-renderers'
import { UserCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * `resource_label`/`field_label` carry raw i18n KEYS off the wire (e.g.
 * `navigation.requestManagement`, `requestManagement.columns.source`): the
 * server-side `ProtectedField` value object never resolves them to display
 * text (spec 0078, `ProtectedFieldRegistry`) — the grid-cell equivalent of
 * the `t(request.field_label)` call the detail view makes.
 */
function TranslatedKeyCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell align="left" />
  }
  return <span className="truncate">{t(value)}</span>
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0078, the 12
 * columns in the frozen `data_contract` order). `status` needs no entry
 * here: it is a native `badge` column, so the generic table already resolves
 * it through the shared `BadgeCell` (`column-defaults.tsx`'s
 * `resolveCellRenderer` fallback) — a per-id override would just shadow that
 * for no reason. `subject_label`/`current_label`/`requested_label`/`reason`/
 * `handling_note` are plain display text, also left to the default text
 * cell.
 */
export const fieldChangeRequestColumnRenderers: TableRendererMap = {
  resource_label: (params) => <TranslatedKeyCell {...params} />,
  field_label: (params) => <TranslatedKeyCell {...params} />,
  requested_by: (params) => <UserCell {...params} />,
  handled_by: (params) => <UserCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
  handled_at: (params) => <DateTimeCell {...params} />,
}
