/**
 * Roadmap view of the Task board (user request 2026-11-25, Planner-style):
 * one section per fase in `sort_order` (then "Senza fase" when it has tasks),
 * each task a bar from its start to its end date on a week grid, today marked
 * by a vertical line. The window is computed on the UNFILTERED board so the
 * timeline does not jump while filtering; the rows are the filtered ones.
 * Read-only on dates: a bar opens the task, where the dates are edited.
 */

import { useEffect, useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import type { BoardStageGroup, BoardTaskNode } from '@/features/work-orders/task-board/task-board-filters'
import {
  TaskBoardRoadmapBar,
  TaskBoardRoadmapRow,
  TaskBoardRoadmapTaskRows,
} from '@/features/work-orders/task-board/task-board-roadmap-row'
import {
  buildRoadmapRange,
  daysBetween,
  mergeSpans,
  roadmapPx,
  roadmapSpan,
  roadmapWeeks,
  type RoadmapTimeline,
} from '@/features/work-orders/task-board/task-board-roadmap'
import { NO_STAGE_ACCENT, stageAccentAt, type StageAccent } from '@/features/work-orders/task-board/task-board-stage-accent'
import type { BoardTask } from '@/features/work-orders/task-board/types'
import { cn } from '@/lib/utils'

/** On open the timeline scrolls so today sits this many weeks from its left edge: the recent past stays in view. */
const WEEKS_BEFORE_TODAY_ON_OPEN = 2

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
  const { t, i18n } = useTranslation()
  const scrollRef = useRef<HTMLDivElement>(null)

  // Step 1: the window and its weeks, over every task of the board
  const timeline = useMemo<RoadmapTimeline>(() => {
    const range = buildRoadmapRange(allGroups.flatMap((group) => flattenNodes(group.roots)), today)
    return { range, todayOffset: daysBetween(range.start, today) }
  }, [allGroups, today])
  const weeks = roadmapWeeks(timeline.range)
  const weekLabel = new Intl.DateTimeFormat(i18n.language, { day: 'numeric', month: 'short', timeZone: 'UTC' })

  // Step 2: bring today into view (a DOM scroll position, the one thing outside React's render)
  const initialScrollLeft = Math.max(roadmapPx(timeline.todayOffset - WEEKS_BEFORE_TODAY_ON_OPEN * 7), 0)
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollLeft = initialScrollLeft
    }
  }, [initialScrollLeft])

  // Step 3: the fasi in order, "Senza fase" only when it holds a task
  const sections = groups
    .map((group, index) => ({ group, accent: group.stage ? stageAccentAt(index) : NO_STAGE_ACCENT }))
    .filter(({ group }) => group.stage !== null || group.roots.length > 0)

  return (
    <section
      aria-label={t('workOrders.taskBoard.roadmap.label')}
      className="overflow-hidden rounded-xl bg-card shadow-sm ring-1 ring-border/70"
    >
      <div ref={scrollRef} className="overflow-x-auto">
        <div className="w-max min-w-full">
          <TaskBoardRoadmapRow
            timeline={timeline}
            className="h-8 bg-card"
            label={<span className="text-xs font-medium text-muted-foreground">{t('workOrders.taskBoard.roadmap.taskColumn')}</span>}
          >
            {weeks.map((week) => (
              <span
                key={week.monday}
                className="absolute top-1/2 -translate-y-1/2 pl-1.5 text-[11px] whitespace-nowrap text-muted-foreground tabular-nums"
                style={{ left: roadmapPx(week.offset) }}
              >
                {weekLabel.format(new Date(`${week.monday}T00:00:00Z`))}
              </span>
            ))}
          </TaskBoardRoadmapRow>

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
