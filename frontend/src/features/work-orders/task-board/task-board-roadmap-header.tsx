/**
 * The top of the Roadmap view: `TaskBoardRoadmapControls` (the Settimane /
 * Mesi / Anno scale toggle and the "Oggi" jump) and `TaskBoardRoadmapTimeHeader`
 * (two header rows: the groups, months or years, above the columns, weeks or
 * months). Geometry comes from `task-board-roadmap.ts`.
 */

import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { TaskBoardRoadmapRow } from '@/features/work-orders/task-board/task-board-roadmap-row'
import {
  ROADMAP_SCALE_CONFIGS,
  ROADMAP_SCALES,
  roadmapPx,
  roadmapSlices,
  type RoadmapScale,
  type RoadmapTimeline,
} from '@/features/work-orders/task-board/task-board-roadmap'
import { cn } from '@/lib/utils'

/** The column label: the week's Monday ("16 nov") or the month ("nov"); the group label: the month in full or the year. */
const COLUMN_FORMATS: Record<RoadmapScale, Intl.DateTimeFormatOptions> = {
  week: { day: 'numeric', month: 'short' },
  month: { month: 'short' },
  year: { month: 'short' },
}
const GROUP_FORMATS: Record<RoadmapScale, Intl.DateTimeFormatOptions> = {
  week: { month: 'long', year: 'numeric' },
  month: { year: 'numeric' },
  year: { year: 'numeric' },
}

const COLUMN_LABEL_CLASS = 'absolute top-1/2 -translate-y-1/2 pl-1.5 text-[11px] whitespace-nowrap text-muted-foreground tabular-nums'
/** A group spans its whole stretch, with a line where it starts, so a partial first month still reads as one block. */
const GROUP_LABEL_CLASS =
  'absolute inset-y-0 flex items-center truncate border-l border-border pl-1.5 text-[11px] font-medium whitespace-nowrap text-foreground tabular-nums'

interface TaskBoardRoadmapControlsProps {
  scale: RoadmapScale
  onScaleChange: (scale: RoadmapScale) => void
  onScrollToToday: () => void
}

export function TaskBoardRoadmapControls({ scale, onScaleChange, onScrollToToday }: TaskBoardRoadmapControlsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex items-center justify-end gap-2 border-b border-border/70 px-3 py-1.5">
      <Button type="button" size="xs" variant="secondary" className="h-7 px-2.5" onClick={onScrollToToday}>
        {t('workOrders.taskBoard.roadmap.today')}
      </Button>
      <div
        role="group"
        aria-label={t('workOrders.taskBoard.roadmap.scaleLabel')}
        className="flex h-8 items-center gap-0.5 rounded-md bg-muted/60 p-0.5"
      >
        {ROADMAP_SCALES.map((option) => (
          <Button
            key={option}
            type="button"
            size="xs"
            variant={scale === option ? 'default' : 'ghost'}
            className={cn('h-7 px-2.5', scale !== option && 'text-muted-foreground')}
            onClick={() => onScaleChange(option)}
            aria-pressed={scale === option}
          >
            {t(`workOrders.taskBoard.roadmap.scales.${option}`)}
          </Button>
        ))}
      </div>
    </div>
  )
}

interface TaskBoardRoadmapTimeHeaderProps {
  scale: RoadmapScale
  timeline: RoadmapTimeline
}

export function TaskBoardRoadmapTimeHeader({ scale, timeline }: TaskBoardRoadmapTimeHeaderProps) {
  const { t, i18n } = useTranslation()
  const groups = roadmapSlices(timeline.range, ROADMAP_SCALE_CONFIGS[scale].groupUnit)
  const groupFormat = new Intl.DateTimeFormat(i18n.language, { ...GROUP_FORMATS[scale], timeZone: 'UTC' })
  const columnFormat = new Intl.DateTimeFormat(i18n.language, { ...COLUMN_FORMATS[scale], timeZone: 'UTC' })
  const asDate = (date: string) => new Date(`${date}T00:00:00Z`)

  return (
    <>
      <TaskBoardRoadmapRow timeline={timeline} className="min-h-7 bg-card" label={null}>
        {groups.map((group) => (
          <span
            key={group.start}
            className={GROUP_LABEL_CLASS}
            style={{ left: roadmapPx(timeline, group.offset), width: roadmapPx(timeline, group.days) }}
          >
            {groupFormat.format(asDate(group.start))}
          </span>
        ))}
      </TaskBoardRoadmapRow>
      <TaskBoardRoadmapRow
        timeline={timeline}
        className="min-h-7 border-t border-border/60 bg-card"
        label={<span className="text-xs font-medium text-muted-foreground">{t('workOrders.taskBoard.roadmap.taskColumn')}</span>}
      >
        {timeline.columns.map((column) => (
          <span key={column.start} className={COLUMN_LABEL_CLASS} style={{ left: roadmapPx(timeline, column.offset) }}>
            {columnFormat.format(asDate(column.start))}
          </span>
        ))}
      </TaskBoardRoadmapRow>
    </>
  )
}
