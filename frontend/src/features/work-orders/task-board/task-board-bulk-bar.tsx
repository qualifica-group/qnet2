/**
 * Floating bulk-action bar (D-7, AC-028): appears sticky above the bottom
 * edge as soon as a task is selected, a Tailwind slide-in/opacity transition
 * (no new dependency). Each action button opens its own dialog; the dialog
 * itself owns the `useBulkBoardTaskAction` submit and the success toast
 * (`showBulkResultToast`) — this component only tracks WHICH dialog is open
 * (`useTaskBoardBulkBar`) and clears the selection once one succeeds.
 */

import { Ban, Calendar, Check, Flag, RotateCcw, UserPlus, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { useTaskBoardBulkBar } from '@/features/work-orders/task-board/use-task-board-bulk-bar'
import { TaskBoardBulkAssignDialog } from '@/features/work-orders/task-board/task-board-bulk-assign-dialog'
import { TaskBoardBulkCompleteDialog } from '@/features/work-orders/task-board/task-board-bulk-complete-dialog'
import { TaskBoardBulkConfirmDialog } from '@/features/work-orders/task-board/task-board-bulk-confirm-dialog'
import { TaskBoardBulkPriorityDialog } from '@/features/work-orders/task-board/task-board-bulk-priority-dialog'
import { TaskBoardBulkDatesDialog } from '@/features/work-orders/task-board/task-board-bulk-dates-dialog'
import type { BulkTaskAction } from '@/features/work-orders/task-board/types'

const ACTION_ICONS: Record<BulkTaskAction, typeof UserPlus> = {
  assign: UserPlus,
  complete: Check,
  uncomplete: RotateCcw,
  block: Ban,
  priority: Flag,
  dates: Calendar,
}

const ACTIONS: BulkTaskAction[] = ['assign', 'complete', 'uncomplete', 'block', 'priority', 'dates']

interface TaskBoardBulkBarProps {
  workOrderId: number
  selectedTaskIds: number[]
  onClear: () => void
}

export function TaskBoardBulkBar({ workOrderId, selectedTaskIds, onClear }: TaskBoardBulkBarProps) {
  const { t } = useTranslation()
  const { activeAction, openDialog, closeDialog } = useTaskBoardBulkBar()
  const isVisible = selectedTaskIds.length > 0

  const handleSuccess = () => {
    closeDialog()
    onClear()
  }

  return (
    <>
      <div
        className={cn(
          'pointer-events-none fixed inset-x-0 bottom-4 z-30 flex justify-center px-4 transition-all duration-200 ease-out',
          isVisible ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0',
        )}
        aria-hidden={!isVisible}
      >
        <div className="pointer-events-auto flex flex-wrap items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-2 shadow-lg">
          <span className="px-1 text-xs font-medium tabular-nums whitespace-nowrap">
            {t('workOrders.taskBoard.bulk.selectedCount', { count: selectedTaskIds.length })}
          </span>
          <div className="h-4 w-px shrink-0 bg-border" aria-hidden="true" />
          {ACTIONS.map((action) => {
            const Icon = ACTION_ICONS[action]
            return (
              <Button
                key={action}
                type="button"
                variant="outline"
                size="xs"
                className="gap-1.5"
                onClick={() => openDialog(action)}
              >
                <Icon className="size-3.5" aria-hidden="true" />
                {t(`workOrders.taskBoard.bulk.action.${action}`)}
              </Button>
            )
          })}
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            onClick={onClear}
            aria-label={t('common.clear')}
          >
            <X className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
      </div>

      {activeAction === 'assign' ? (
        <TaskBoardBulkAssignDialog
          workOrderId={workOrderId}
          taskIds={selectedTaskIds}
          onClose={closeDialog}
          onSuccess={handleSuccess}
        />
      ) : null}
      {activeAction === 'complete' ? (
        <TaskBoardBulkCompleteDialog
          workOrderId={workOrderId}
          taskIds={selectedTaskIds}
          onClose={closeDialog}
          onSuccess={handleSuccess}
        />
      ) : null}
      {activeAction === 'uncomplete' || activeAction === 'block' ? (
        <TaskBoardBulkConfirmDialog
          action={activeAction}
          workOrderId={workOrderId}
          taskIds={selectedTaskIds}
          onClose={closeDialog}
          onSuccess={handleSuccess}
        />
      ) : null}
      {activeAction === 'priority' ? (
        <TaskBoardBulkPriorityDialog
          workOrderId={workOrderId}
          taskIds={selectedTaskIds}
          onClose={closeDialog}
          onSuccess={handleSuccess}
        />
      ) : null}
      {activeAction === 'dates' ? (
        <TaskBoardBulkDatesDialog
          workOrderId={workOrderId}
          taskIds={selectedTaskIds}
          onClose={closeDialog}
          onSuccess={handleSuccess}
        />
      ) : null}
    </>
  )
}
