/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import { DateTimeCell } from '@/features/table/cell-renderers'
import { BooleanBadgeCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/** Renders the `parent` column: the parent sector's name, em dash for a root sector. */
function ParentCell({ value }: ICellRendererParams) {
  const parent = value as { id: number; name: string } | null
  return parent ? <span>{parent.name}</span> : <span className="text-muted-foreground">—</span>
}

/**
 * Custom cell renderers keyed by the backend column `id`. `name` falls back
 * to the AG Grid default text cell; `created_at` reuses the shared
 * domain-agnostic renderer (mirrors `referentTypeColumnRenderers`).
 */
export const sectorColumnRenderers: TableRendererMap = {
  parent: (params) => <ParentCell {...params} />,
  is_active: (params) => <BooleanBadgeCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
