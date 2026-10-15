import type { ICellRendererParams } from 'ag-grid-community'
import { Building2 } from 'lucide-react'
import { RelationCell } from '@/features/table/rich-cells'

/**
 * Spec 0267 D-8: a multi-offer row carries the distinct values already joined
 * by " · " as a plain string, which `RelationCell` (object-only) would draw empty.
 */
export function RegistryCell(params: ICellRendererParams) {
  if (typeof params.value === 'string' && params.value !== '') {
    return (
      <div className="flex h-full items-center gap-1.5 overflow-hidden">
        <Building2 aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground/70" />
        <span className="truncate" title={params.value}>
          {params.value}
        </span>
      </div>
    )
  }
  return <RelationCell {...params} icon={Building2} />
}
