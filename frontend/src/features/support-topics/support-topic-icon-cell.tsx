import type { ICellRendererParams } from 'ag-grid-community'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { isKnownIconName } from '@/features/custom-fields/icon-catalog'
import { EmptyCell } from '@/features/table/cell-renderers'

/** The stored icon name is an internal key: show the icon the user sees in the request dialog instead. */
export function SupportTopicIconCell({ value }: ICellRendererParams) {
  if (typeof value !== 'string' || !isKnownIconName(value)) {
    return <EmptyCell />
  }
  return (
    <div className="flex h-full items-center justify-center" title={value}>
      <DynamicIcon name={value} className="size-4 text-foreground" />
    </div>
  )
}
