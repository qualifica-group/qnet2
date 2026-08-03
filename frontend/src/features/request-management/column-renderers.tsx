/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { MapPin, Radio } from 'lucide-react'
import type { ProductLineCellValue } from '@/features/product-lines/product-lines-cell-editor'
import { DateTimeCell, EmptyCell } from '@/features/table/cell-renderers'
import { RefNamesCell, RelationCell, StatusBadgeCell } from '@/features/table/rich-cells'
import { UserCell } from '@/features/table/user-cell'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * A plain text cell: truncates with a native tooltip on overflow and falls
 * back to the shared em-dash EmptyCell when the value is null/empty. Reused by
 * every display-only text column of the request-management worklist
 * (`operator_ga2`, the client anagraphic fields).
 */
function TextCell({ value }: ICellRendererParams) {
  if (value === null || value === undefined || value === '') {
    return <EmptyCell align="left" />
  }
  const text = String(value)
  return (
    <div className="flex h-full items-center overflow-hidden">
      <span className="truncate" title={text}>
        {text}
      </span>
    </div>
  )
}

/**
 * The "Categoria prodotto" cell (spec 0075): the row projects the request's
 * own {funzione aziendale, categoria} pairs — what the inline editor commits —
 * so the cell renders the CATEGORY names out of them, comma-joined, with the
 * full pair list as its native tooltip.
 */
function ProductCategoriesCell({ value }: ICellRendererParams) {
  const pairs = Array.isArray(value) ? (value as ProductLineCellValue[]) : []

  if (pairs.length === 0) {
    return <EmptyCell align="left" />
  }

  const label = pairs.map((pair) => pair.product_category_name).join(', ')
  const tooltip = pairs.map((pair) => `${pair.business_function_name} › ${pair.product_category_name}`).join('\n')

  return (
    <div className="flex h-full items-center overflow-hidden">
      <span className="truncate" title={tooltip}>
        {label}
      </span>
    </div>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id` (spec 0049). `source`
 * ("Fonte", user directive 2026-07-31) is a plain `{id, name}` relation, so it
 * reuses the shared `RelationCell` exactly as the leads grid does for the same
 * entity; `general_notes` is free text, truncated by the local `TextCell` with
 * the full note as its native tooltip. The GA2
 * `operator_ga2` renders as the shared `UserCell` (avatar + hover-card that
 * opens the user's profile Sheet — same component as the opportunities
 * `supervisor` column). `operational_site` (spec 0056) has no `name`, only the
 * server-composed `label`, which `RelationCell` reads. The working state the
 * operator advances (`workflow_status`) renders as a colored badge via the
 * shared `StatusBadgeCell`; the client's PersonalData anagraphic fields are
 * display-only text, while the product categories render their own pair
 * projection (spec 0075). `next_callback_at`
 * (spec 0052) reuses the shared `DateTimeCell` in its `optionalTime` mode: the
 * hour is optional (user directive 2026-07-31), so a callback planned without
 * one shows as a plain date instead of a misleading "00:00".
 */
export const requestManagementColumnRenderers: TableRendererMap = {
  source: (params) => <RelationCell {...params} icon={Radio} />,
  product_categories: (params) => <ProductCategoriesCell {...params} />,
  products_of_interest: (params) => <RefNamesCell {...params} />,
  general_notes: (params) => <TextCell {...params} />,
  operator_ga2: (params) => <UserCell {...params} />,
  operational_site: (params) => <RelationCell {...params} icon={MapPin} />,
  workflow_status: (params) => <StatusBadgeCell {...params} />,
  first_name: (params) => <TextCell {...params} />,
  last_name: (params) => <TextCell {...params} />,
  tax_code: (params) => <TextCell {...params} />,
  phone: (params) => <TextCell {...params} />,
  next_callback_at: (params) => <DateTimeCell {...params} optionalTime />,
}
