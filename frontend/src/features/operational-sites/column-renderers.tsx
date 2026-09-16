/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Em-dash placeholder for an empty/unknown cell value. */
function EmptyCell() {
  return (
    <div className="flex h-full items-center justify-center">
      <span className="text-muted-foreground">—</span>
    </div>
  )
}

/**
 * Renders a derived address text column (comune/via/cap/provincia/regione):
 * a single truncated line with the full value in the native tooltip, so a
 * long street address never breaks the row height.
 */
function AddressTextCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return <EmptyCell />
  }
  return (
    <div className="flex h-full items-center px-2" title={value}>
      <span className="truncate">{value}</span>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. `alias` is the site's
 * own text column; the geo/address columns (city/street/postal_code/province/
 * region) are derived from its primary address (spec 0011); `is_active` renders
 * the shared yes/no badge (spec 0135); `created_at` reuses
 * the shared domain-agnostic renderer so datetime formatting is not
 * re-implemented per domain.
 */
export const operationalSiteColumnRenderers: TableRendererMap = {
  alias: (params) => <AddressTextCell {...params} />,
  city: (params) => <AddressTextCell {...params} />,
  street: (params) => <AddressTextCell {...params} />,
  postal_code: (params) => <AddressTextCell {...params} />,
  province: (params) => <AddressTextCell {...params} />,
  region: (params) => <AddressTextCell {...params} />,
  is_active: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
