import type { ICellRendererParams } from 'ag-grid-community'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'

/** The name as a badge in the type's own color (spec 0249 D-6); `nature` uses the generic enum badge. */
export function CostTypeNameCell({ value, data }: ICellRendererParams) {
  if (typeof value !== 'string' || value === '') {
    return null
  }
  return (
    <div className="flex h-full items-center overflow-hidden">
      <CostTypeBadge name={value} color={(data as { color?: string | null } | undefined)?.color} />
    </div>
  )
}
