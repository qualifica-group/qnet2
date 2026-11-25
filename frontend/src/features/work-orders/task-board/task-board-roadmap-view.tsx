/**
 * Roadmap view of the Task board (user request 2026-11-25, Planner-style):
 * one section per fase in `sort_order` (then "Senza fase" when it has tasks),
 * each task a bar from its start to its end date on a time grid, today marked
 * by a vertical line. The grid reads by week, month or year (Settimane / Mesi /
 * Anno, remembered in the browser). The window is computed on the UNFILTERED board so the
 * timeline does not jump while filtering; the rows are the filtered ones.
 * Read-only on dates: a bar opens the task, where the dates are edited.
 */

import { useEffect, useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import type { BoardStageGroup, BoardTaskNode } from '@/features/work-orders/task-board/task-board-filters'
import { TaskBoardRoadmapControls, TaskBoardRoadmapTimeHeader } from '@/features/work-orders/task-board/task-board-roadmap-header'
import {
  TaskBoardRoadmapBar,
  TaskBoardRoadmapRow,
  TaskBoardRoadmapTaskRows,
} from '@/features/work-orders/task-board/task-board-roadmap-row'
import {
  ROADMAP_SCALE_CONFIGS,
  buildRoadmapRange,
  daysBetween,
  mergeSpans,
  roadmapPx,
  roadmapSlices,
  roadmapSpan,
  type RoadmapTimeline,
} from '@/features/work-orders/task-board/task-board-roadmap'
import { NO_STAGE_ACCENT, stageAccentAt, type StageAccent } from '@/features/work-orders/task-board/task-board-stage-accent'
import type { BoardTask } from '@/features/work-orders/task-board/types'
import { useRoadmapScale } from '@/features/work-orders/task-board/use-roadmap-scale'
import { cn } from '@/lib/utils'

function flattenNodes(nodes: BoardTaskNode[]): BoardTask[] {
  return nodes.flatMap((node) => [node.task, ...flattenNodes(node.children)])
}

interface TaskBoardRoadmapViewProps {
  groups: BoardStageGroup[]
  /** The same grouping, filters aside: the date window is computed on it. */
  allGroups: BoardStageGroup[]
  today: string
  isReadOnly: boolean
  selectedTaskIds: Set<number>
  onToggleSelection: (taskId: number) => void
  onOpenTask: (taskId: number) => void
}

export function TaskBoardRoadmapView({
  groups,
  allGroups,
  today,
  isReadOnly,
  selectedTaskIds,
  onToggleSelection,
  onOpenTask,
}: TaskBoardRoadmapViewProps) {
  const { t } = useTranslation()
  const scrollRef = useRef<HTMLDivElement>(null)
  const { scale, setScale } = useRoadmapScale()

  // Step 1: the window and its columns at the chosen scale, over every task of the board
  const timeline = useMemo<RoadmapTimeline>(() => {
    const config = ROADMAP_SCALE_CONFIGS[scale]
    const range = buildRoadmapRange(allGroups.flatMap((group) => flattenNodes(group.roots)), today, scale)
    return {
      range,
      todayOffset: daysBetween(range.start, today),
      dayWidthPx: config.dayWidthPx,
      columns: roadmapSlices(range, config.columnUnit),
    }
  }, [allGroups, today, scale])

  // Step 2: bring today into view on open, on a scale change and on "Oggi" (a DOM scroll position, the one thing outside React's render)
  const todayScrollLeft = Math.max(roadmapPx(timeline, timeline.todayOffset - ROADMAP_SCALE_CONFIGS[scale].daysBeforeTodayOnOpen), 0)
  const scrollToToday = () => {
    if (scrollRef.current) {
      scrollRef.current.scrollLeft = todayScrollLeft
    }
  }
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollLeft = todayScrollLeft
    }
  }, [todayScrollLeft])

  // Step 3: the fasi in order, "Senza fase" only when it holds a task
  const sections = groups
    .map((group, index) => ({ group, accent: group.stage ? stageAccentAt(index) : NO_STAGE_ACCENT }))
    .filter(({ group }) => group.stage !== null || group.roots.length > 0)

  return (
    <section
      aria-label={t('workOrders.taskBoard.roadmap.label')}
      className="overflow-hidden rounded-xl bg-card shadow-sm ring-1 ring-border/70"
    >
      <TaskBoardRoadmapControls scale={scale} onScaleChange={setScale} onScrollToToday={scrollToToday} />
      <div ref={scrollRef} className="overflow-x-auto">
        <div className="w-max min-w-full">
          <TaskBoardRoadmapTimeHeader scale={scale} timeline={timeline} />

          {sections.map(({ group, accent }) => (
            <RoadmapStageSection
              key={group.stage?.id ?? 'none'}
              group={group}
              accent={accent}
              timeline={timeline}
              today={today}
              isReadOnly={isReadOnly}
              selectedTaskIds={selectedTaskIds}
              onToggleSelection={onToggleSelection}
              onOpenTask={onOpenTask}
            />
          ))}
        </div>
      </div>
    </section>
  )
}

interface RoadmapStageSectionProps {
  group: BoardStageGroup
  accent: StageAccent
  timeline: RoadmapTimeline
  today: string
  isReadOnly: boolean
  selectedTaskIds: Set<number>
  onToggleSelection: (taskId: number) => void
  onOpenTask: (taskId: number) => void
}

/** A fase's header row (name, count, the stretch its tasks cover) and its task rows. */
function RoadmapStageSection({
  group,
  accent,
  timeline,
  today,
  isReadOnly,
  selectedTaskIds,
  onToggleSelection,
  onOpenTask,
}: RoadmapStageSectionProps) {
  const { t } = useTranslation()
  const { stage, roots } = group
  const stageName = stage ? stage.name : t('workOrders.taskBoard.noStage')
  const stageSpan = mergeSpans(flattenNodes(roots).map(roadmapSpan))

  return (
    <div role="group" aria-label={stageName}>
      <TaskBoardRoadmapRow
        timeline={timeline}
        className="border-t border-border bg-surface/60"
        label={
          <>
            <span aria-hidden="true" className={cn('size-2.5 shrink-0 rounded-full', accent.dot)} />
            <span className="min-w-0 truncate text-xs font-semibold text-foreground">{stageName}</span>
            <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium tabular-nums text-muted-foreground">
              {roots.length}
            </span>
          </>
        }
      >
        {stageSpan ? (
          <TaskBoardRoadmapBar span={stageSpan} timeline={timeline} className={cn('h-1.5! opacity-60', accent.dot)} label={stageName} />
        ) : null}
      </TaskBoardRoadmapRow>

      {roots.map((node) => (
        <TaskBoardRoadmapTaskRows
          key={node.task.id}
          node={node}
          depth={0}
          timeline={timeline}
          accent={accent}
          today={today}
          isSelectable={!isReadOnly}
          selectedTaskIds={selectedTaskIds}
          onToggleSelection={onToggleSelection}
          onOpenTask={onOpenTask}
        />
      ))}
    </div>
  )
}
