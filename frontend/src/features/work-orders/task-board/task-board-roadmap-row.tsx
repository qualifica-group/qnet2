/**
 * The rows of the Roadmap view: `TaskBoardRoadmapRow` is the shared shell (a
 * label cell pinned on the left, the timeline cell with the week grid and the
 * today line on the right), `TaskBoardRoadmapTaskRows` a task with its bar and,
 * nested below it, its sub-tasks. Positions come from `task-board-roadmap.ts`
 * in days; this file only turns them into pixels through `roadmapPx`, at the
 * timeline's scale.
 */

import type { CSSProperties, ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import type { BoardTaskNode } from '@/features/work-orders/task-board/task-board-filters'
import { isClosedTask, isOverdue } from '@/features/work-orders/task-board/task-board-metrics'
import {
  roadmapBar,
  roadmapPx,
  roadmapSpan,
  type RoadmapSpan,
  type RoadmapTimeline,
} from '@/features/work-orders/task-board/task-board-roadmap'
import type { StageAccent } from '@/features/work-orders/task-board/task-board-stage-accent'
import type { BoardTask } from '@/features/work-orders/task-board/types'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

const INDENT_REM = 1.25
const INDENT_DEPTH_MAX = 3
const MILESTONE_SIZE_PX = 12
/** At the Anno scale a one-day task is 2px wide: never narrower than this, so it stays visible and clickable. */
const MIN_BAR_WIDTH_PX = 6

/** The label column stays put while the timeline scrolls under it: same rung as the card, so the bars slide below it. */
const ROADMAP_LABEL_CELL_CLASS =
  'sticky left-0 z-10 flex w-44 shrink-0 items-center gap-2 border-r border-border/70 bg-card px-3 sm:w-72'

const GRID_LINE = 'linear-gradient(var(--border), var(--border))'
const gridStyles = new WeakMap<RoadmapTimeline, CSSProperties>()

/**
 * The column lines drawn as one 1px background layer per column, so no row
 * renders one element per column; months are not all as wide, hence one
 * positioned layer each instead of a repeating pattern. Built once per timeline.
 */
function gridStyleOf(timeline: RoadmapTimeline): CSSProperties {
  let style = gridStyles.get(timeline)
  if (!style) {
    style = {
      backgroundImage: timeline.columns.map(() => GRID_LINE).join(', '),
      backgroundPosition: timeline.columns.map((column) => `${roadmapPx(timeline, column.offset)}px 0`).join(', '),
      backgroundSize: '1px 100%',
      backgroundRepeat: 'no-repeat',
    }
    gridStyles.set(timeline, style)
  }

  return style
}

interface TaskBoardRoadmapRowProps {
  timeline: RoadmapTimeline
  label: ReactNode
  children?: ReactNode
  className?: string
}

export function TaskBoardRoadmapRow({ timeline, label, children, className }: TaskBoardRoadmapRowProps) {
  return (
    <div className={cn('flex min-h-9', className)}>
      <div className={ROADMAP_LABEL_CELL_CLASS}>{label}</div>
      <div className="relative shrink-0" style={{ ...gridStyleOf(timeline), width: roadmapPx(timeline, timeline.range.days) }}>
        <span
          aria-hidden="true"
          className="absolute inset-y-0 w-px bg-primary/70"
          style={{ left: roadmapPx(timeline, timeline.todayOffset + 0.5) }}
        />
        {children}
      </div>
    </div>
  )
}

/** Closed reads as done, then overdue, then blocked; an ordinary task wears its fase's colour. */
function barToneClass(task: BoardTask, today: string, accent: StageAccent): string {
  if (isClosedTask(task)) {
    return 'bg-muted-foreground/40'
  }
  if (isOverdue(task, today)) {
    return 'bg-destructive'
  }
  if (task.is_blocked) {
    return 'bg-warning'
  }

  return accent.dot
}

interface RoadmapBarProps {
  span: RoadmapSpan
  timeline: RoadmapTimeline
  className: string
  label: string
  onClick?: () => void
}

/** A bar for a dated stretch, a diamond for a one-date milestone; a button when it opens something. */
export function TaskBoardRoadmapBar({ span, timeline, className, label, onClick }: RoadmapBarProps) {
  const bar = roadmapBar(span, timeline.range)
  const style: CSSProperties = span.isMilestone
    ? { left: roadmapPx(timeline, bar.offset + 0.5) - MILESTONE_SIZE_PX / 2, width: MILESTONE_SIZE_PX, height: MILESTONE_SIZE_PX }
    : { left: roadmapPx(timeline, bar.offset) + 1, width: Math.max(roadmapPx(timeline, bar.length) - 2, MIN_BAR_WIDTH_PX) }
  const shapeClass = cn(
    'absolute top-1/2 -translate-y-1/2',
    span.isMilestone ? 'rotate-45 rounded-[2px]' : 'h-4 rounded-md',
    className,
  )

  return onClick ? (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      title={label}
      style={style}
      className={cn(shapeClass, 'cursor-pointer outline-none hover:shadow-md focus-visible:ring-[3px] focus-visible:ring-ring/50')}
    />
  ) : (
    <span aria-hidden="true" style={style} className={shapeClass} />
  )
}

interface TaskBoardRoadmapTaskRowsProps {
  node: BoardTaskNode
  depth: number
  timeline: RoadmapTimeline
  accent: StageAccent
  today: string
  /** Only root rows are selectable (the bulk bar acts on roots, as in the other views). */
  isSelectable: boolean
  selectedTaskIds: Set<number>
  onToggleSelection: (taskId: number) => void
  onOpenTask: (taskId: number) => void
}

export function TaskBoardRoadmapTaskRows({
  node,
  depth,
  timeline,
  accent,
  today,
  isSelectable,
  selectedTaskIds,
  onToggleSelection,
  onOpenTask,
}: TaskBoardRoadmapTaskRowsProps) {
  const { t } = useTranslation()
  const { task, children } = node
  const span = roadmapSpan(task)
  const isClosed = isClosedTask(task)
  const states = [task.task_status.name]
  if (isOverdue(task, today)) {
    states.push(t('workOrders.taskBoard.task.overdue'))
  }
  if (task.is_blocked) {
    states.push(t('workOrders.taskBoard.roadmap.blocked'))
  }
  const period = span
    ? span.isMilestone
      ? formatDate(span.start)
      : `${formatDate(span.start)} – ${formatDate(span.end)}`
    : t('workOrders.taskBoard.roadmap.noDates')
  const barLabel = t('workOrders.taskBoard.roadmap.bar', { title: task.title, period, state: states.join(', ') })

  return (
    <>
      <TaskBoardRoadmapRow
        timeline={timeline}
        className="border-t border-border/60"
        label={
          <>
            {isSelectable && depth === 0 ? (
              <Checkbox
                checked={selectedTaskIds.has(task.id)}
                onCheckedChange={() => onToggleSelection(task.id)}
                aria-label={task.title}
                className="shrink-0"
              />
            ) : null}
            <button
              type="button"
              onClick={() => onOpenTask(task.id)}
              title={task.title}
              style={{ paddingLeft: `${Math.min(depth, INDENT_DEPTH_MAX) * INDENT_REM}rem` }}
              className={cn(
                'min-w-0 flex-1 truncate rounded-sm text-left text-xs outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50',
                depth > 0 ? 'text-muted-foreground' : 'font-medium text-foreground',
                isClosed && 'line-through',
              )}
            >
              {task.title}
            </button>
          </>
        }
      >
        {span ? (
          <TaskBoardRoadmapBar
            span={span}
            timeline={timeline}
            className={cn(barToneClass(task, today, accent), depth > 0 && 'opacity-70')}
            label={barLabel}
            onClick={() => onOpenTask(task.id)}
          />
        ) : (
          <span
            className="absolute top-1/2 -translate-y-1/2 pl-2 text-[11px] whitespace-nowrap text-muted-foreground"
            style={{ left: roadmapPx(timeline, timeline.todayOffset + 1) }}
          >
            {t('workOrders.taskBoard.roadmap.noDates')}
          </span>
        )}
      </TaskBoardRoadmapRow>
      {children.map((child) => (
        <TaskBoardRoadmapTaskRows
          key={child.task.id}
          node={child}
          depth={depth + 1}
          timeline={timeline}
          accent={accent}
          today={today}
          isSelectable={isSelectable}
          selectedTaskIds={selectedTaskIds}
          onToggleSelection={onToggleSelection}
          onOpenTask={onOpenTask}
        />
      ))}
    </>
  )
}
