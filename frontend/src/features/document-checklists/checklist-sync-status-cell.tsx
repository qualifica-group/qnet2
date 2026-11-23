import type { ICellRendererParams } from 'ag-grid-community'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { ChecklistSyncStatusBadge } from '@/features/document-checklists/checklist-sync-status'
import type { DocumentChecklistSyncStatus } from '@/features/document-checklists/types'

const SYNC_STATUSES: readonly string[] = ['pending', 'synced', 'failed']

function isSyncStatus(value: unknown): value is DocumentChecklistSyncStatus {
  return typeof value === 'string' && SYNC_STATUSES.includes(value)
}

/** Grid cell of the sync state: badge with text + icon, and the failure reason as tooltip when failed. */
export function ChecklistSyncStatusCell({ value, data }: ICellRendererParams) {
  if (!isSyncStatus(value)) {
    return <EmptyCell />
  }

  const error = value === 'failed' && typeof data?.sync_error === 'string' ? data.sync_error : null

  return (
    <div className={CELL_WRAPPER}>
      {error ? (
        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger asChild>
              <span tabIndex={0} className="inline-flex rounded-md focus-visible:ring-2 focus-visible:ring-ring">
                <ChecklistSyncStatusBadge status={value} />
              </span>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs whitespace-pre-wrap">{error}</TooltipContent>
          </Tooltip>
        </TooltipProvider>
      ) : (
        <ChecklistSyncStatusBadge status={value} />
      )}
    </div>
  )
}
