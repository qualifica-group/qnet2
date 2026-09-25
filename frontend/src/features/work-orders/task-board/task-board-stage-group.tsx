/**
 * One phase section of the list view (D-5), in two clearly separate layers
 * (user directive 2026-09-22, "troppo piatto"): the HEADER sits directly on
 * the board canvas as a title row (colour dot, name, task count, completion
 * and hours roll-up, "Task", "..." menu), and the TASKS live in a raised
 * white card below it, edged with the phase colour, rows split by hairlines
 * rather than boxed one by one. "Senza fase" is the SAME component with
 * `group.stage === null`: no drag handle and no menu, still a drop target.
 */

import { useState, type CSSProperties, type ReactNode } from 'react'
import { useDroppable } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { ChevronDown, ChevronRight, Lock, Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'
import { computeStageMetrics } from '@/features/work-orders/task-board/task-board-metrics'
import type { StageAccent } from '@/features/work-orders/task-board/task-board-stage-accent'
import { TaskBoardStageMenu } from '@/features/work-orders/task-board/task-board-stage-menu'
import { TaskBoardStageSummary } from '@/features/work-orders/task-board/task-board-task-meta'
import { TaskBoardTaskRow } from '@/features/work-orders/task-board/task-board-task-row'
import { groupDroppableId } from '@/features/work-orders/task-board/use-task-board-dnd'
import { useRenameWorkOrderStage } from '@/features/work-orders/task-board/use-task-board-mutations'

/** Rung 3 on the rung-2 canvas: the only white surface in a phase, so the tasks read as the content. */
const TASK_CARD_CLASS = 'relative overflow-hidden rounded-xl bg-card shadow-sm ring-1 ring-border/70 transition-shadow'

interface TaskBoardStageGroupProps {
  workOrderId: number
  group: BoardStageGroup
  accent: StageAccent
  isReadOnly: boolean
  isDragOver: boolean
  today: string
  selectedTaskIds: Set<number>
  onToggleSelection: (taskId: number) => void
  onOpenTask: (taskId: number) => void
  onAddTask: (stageId: number | null) => void
  /** The commessa's `unstaged_logged_minutes` (spec 0163 AC-007): read only when `group.stage === null`. */
  unstagedLoggedMinutes?: number
  /** The header's own drag handle, wired by the sortable wrapper in `task-board-list-view.tsx`; `undefined` for "Senza fase" (never draggable). */
  dragHandle?: ReactNode
  /** dnd-kit `useSortable` wiring for the group's own `<li>`, applied by the same wrapper; absent for "Senza fase". */
  sortableRef?: (element: HTMLLIElement | null) => void
  sortableStyle?: CSSProperties
  isDraggingStage?: boolean
}

export function TaskBoardStageGroup({
  workOrderId,
  group,
  accent,
  isReadOnly,
  isDragOver,
  today,
  selectedTaskIds,
  onToggleSelection,
  onOpenTask,
  onAddTask,
  unstagedLoggedMinutes,
  dragHandle,
  sortableRef,
  sortableStyle,
  isDraggingStage,
}: TaskBoardStageGroupProps) {
  const { t } = useTranslation()
  const { stage, roots } = group
  const key = stage ? String(stage.id) : 'none'

  const [isOpen, setIsOpen] = useState(true)
  const [isRenaming, setIsRenaming] = useState(false)
  const [draftName, setDraftName] = useState('')

  const { setNodeRef: setDroppableRef } = useDroppable({ id: groupDroppableId(key) })
  const metrics = computeStageMetrics(roots.map((node) => node.task))
  const loggedMinutes = stage ? stage.logged_minutes : (unstagedLoggedMinutes ?? 0)
  const renameStage = useRenameWorkOrderStage(workOrderId)
  const isClosed = stage?.closed_at != null

  function startRename() {
    setDraftName(stage?.name ?? '')
    // Radix's FocusScope restores focus to the menu trigger the instant the
    // menu unmounts; opening the input in the SAME tick races that restore and
    // blurs it before the user types. One macrotask lets the menu finish first.
    setTimeout(() => setIsRenaming(true), 0)
  }

  function submitRename() {
    const name = draftName.trim()
    if (!stage || name === '' || name === stage.name) {
      setIsRenaming(false)
      return
    }
    renameStage.mutate(
      { stageId: stage.id, name },
      { onSuccess: () => setIsRenaming(false), onError: () => toast.error(t('workOrders.taskBoard.stage.genericError')) },
    )
  }

  return (
    <li ref={sortableRef} style={sortableStyle} className={cn('flex flex-col', isDraggingStage && 'z-10 opacity-70')}>
      <Collapsible open={isOpen} onOpenChange={setIsOpen} className="flex flex-col gap-2">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 px-1">
          <div className="flex min-w-0 flex-1 basis-56 items-center gap-2">
            {dragHandle}
            <CollapsibleTrigger asChild>
              <button
                type="button"
                aria-label={isOpen ? t('workOrders.taskBoard.stage.collapse') : t('workOrders.taskBoard.stage.expand')}
                className="flex shrink-0 items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
              >
                {isOpen ? <ChevronDown className="size-4" aria-hidden="true" /> : <ChevronRight className="size-4" aria-hidden="true" />}
              </button>
            </CollapsibleTrigger>
            <span aria-hidden="true" className={cn('size-2.5 shrink-0 rounded-full', accent.dot)} />

            {isRenaming ? (
              <Input
                value={draftName}
                onChange={(event) => setDraftName(event.target.value)}
                onBlur={submitRename}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    submitRename()
                  } else if (event.key === 'Escape') {
                    setIsRenaming(false)
                  }
                }}
                autoFocus
                className="h-7 max-w-56 text-sm"
              />
            ) : (
              <h3 className="min-w-0 truncate text-sm font-semibold text-foreground">
                {stage ? stage.name : t('workOrders.taskBoard.noStage')}
              </h3>
            )}

            <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground">
              {metrics.count}
            </span>

            {isClosed ? (
              <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                <Lock className="size-3" aria-hidden="true" />
                {t('workOrders.taskBoard.stage.closedOn', { date: formatDate(stage?.closed_at) })}
              </span>
            ) : null}
          </div>

          <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <TaskBoardStageSummary metrics={metrics} loggedMinutes={loggedMinutes} />
            <div className="flex items-center gap-1">
              {!isReadOnly && !isClosed ? (
                <Button size="xs" variant="ghost" className="shrink-0" onClick={() => onAddTask(stage?.id ?? null)}>
                  <Plus aria-hidden="true" />
                  {t('workOrders.taskBoard.stage.addTask')}
                </Button>
              ) : null}
              {!isReadOnly && stage ? <TaskBoardStageMenu workOrderId={workOrderId} stage={stage} onRename={startRename} /> : null}
            </div>
          </div>
        </div>

        <CollapsibleContent>
          <div className={cn(TASK_CARD_CLASS, isDragOver && 'ring-2 ring-primary/50')}>
            <span aria-hidden="true" className={cn('absolute inset-y-0 left-0 w-1', accent.stripe)} />
            <SortableContext items={roots.map((node) => String(node.task.id))} strategy={verticalListSortingStrategy}>
              <ul ref={setDroppableRef} className="flex min-h-12 flex-col divide-y divide-border/60 pl-1">
                {roots.map((node) => (
                  <TaskBoardTaskRow
                    key={node.task.id}
                    node={node}
                    today={today}
                    isReadOnly={isReadOnly}
                    isSelected={selectedTaskIds.has(node.task.id)}
                    onToggleSelection={onToggleSelection}
                    onOpenTask={onOpenTask}
                  />
                ))}
              </ul>
            </SortableContext>
            {roots.length === 0 ? (
              <p className="pointer-events-none absolute inset-0 flex items-center justify-center text-xs text-muted-foreground">
                {t('workOrders.taskBoard.stage.emptyGroup')}
              </p>
            ) : null}
          </div>
        </CollapsibleContent>
      </Collapsible>
    </li>
  )
}
