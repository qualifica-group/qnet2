/**
 * The building blocks of a task row and of a phase summary.
 * `TaskBoardPeople` shows each person as the app's shared avatar +
 * `UserProfileHoverCard` (hover reveals the profile action, click opens the
 * user Sheet), or as plain avatars inside an editable cell, which is itself a
 * button; `TaskBoardHours` turns worked-vs-estimated minutes into a bar plus
 * its number.
 */

import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { Progress } from '@/components/ui/progress'
import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import type { TaskNamedRef } from '@/features/tasks/types'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { TaskBoardStageMetrics } from '@/features/work-orders/task-board/task-board-metrics'
import { cn } from '@/lib/utils'

const PERCENT_MAX = 100
/** People beyond the first shown as bare avatars before the rest collapses into "+N". */
const EXTRA_AVATARS_MAX = 2

interface TaskBoardPeopleProps {
  people: TaskNamedRef[]
  /** Plain avatars with no profile hover card: the cell around them is already a button. */
  isStatic?: boolean
}

export function TaskBoardPeople({ people, isStatic = false }: TaskBoardPeopleProps) {
  const { t } = useTranslation()

  if (people.length === 0) {
    return <span className="text-muted-foreground">{t('workOrders.taskBoard.task.nobody')}</span>
  }

  const [first, ...others] = people
  const extraAvatars = others.slice(0, EXTRA_AVATARS_MAX)
  const hidden = others.slice(EXTRA_AVATARS_MAX)

  const firstPerson = (
    <>
      <UserAvatar name={first.name} size="sm" className="shrink-0" />
      <span className="truncate text-xs text-foreground">{first.name}</span>
    </>
  )

  return (
    <>
      {isStatic ? (
        <span className="flex min-w-0 items-center gap-1.5">{firstPerson}</span>
      ) : (
        <UserProfileHoverCard user={first} triggerClassName="min-w-0 gap-1.5 rounded-md">
          {firstPerson}
        </UserProfileHoverCard>
      )}
      {extraAvatars.map((person) =>
        isStatic ? (
          <UserAvatar key={person.id} name={person.name} size="sm" className="shrink-0" />
        ) : (
          <UserProfileHoverCard key={person.id} user={person} triggerClassName="shrink-0 rounded-full">
            <UserAvatar name={person.name} size="sm" />
          </UserProfileHoverCard>
        ),
      )}
      {hidden.length > 0 ? (
        <span className="shrink-0 text-muted-foreground" title={hidden.map((person) => person.name).join(', ')}>
          +{hidden.length}
        </span>
      ) : null}
    </>
  )
}

interface TaskBoardHoursProps {
  actualMinutes: number
  estimatedMinutes: number | null
  label: string
}

export function TaskBoardHours({ actualMinutes, estimatedMinutes, label }: TaskBoardHoursProps) {
  const { t } = useTranslation()

  if (estimatedMinutes === null || estimatedMinutes === 0) {
    return (
      <span className="tabular-nums">
        {formatMinutesLabel(actualMinutes)}{' '}
        <span className="text-muted-foreground">({t('workOrders.taskBoard.task.noEstimate')})</span>
      </span>
    )
  }

  const isOver = actualMinutes > estimatedMinutes
  const percentage = Math.round((actualMinutes / estimatedMinutes) * PERCENT_MAX)

  return (
    <>
      <Progress
        value={Math.min(percentage, PERCENT_MAX)}
        size="xs"
        className="w-14 shrink-0"
        indicatorClassName={cn(isOver && 'bg-destructive')}
        aria-label={label}
      />
      <span className={cn('tabular-nums', isOver && 'font-medium text-destructive')}>
        {formatMinutesLabel(actualMinutes)} / {formatMinutesLabel(estimatedMinutes)}
      </span>
      {isOver ? (
        <AlertTriangle
          className="size-3.5 shrink-0 text-destructive"
          role="img"
          aria-label={t('workOrders.taskBoard.task.overEstimate')}
        />
      ) : null}
    </>
  )
}

interface TaskBoardStageSummaryProps {
  metrics: TaskBoardStageMetrics
  /**
   * Sum of the fase's own `time_entries.minutes` (spec 0163 AC-007): the
   * fase's `logged_minutes`, or the commessa's `unstaged_logged_minutes` for
   * "Senza fase" — a DIFFERENT total than `metrics.actualMinutes` (task-based
   * only), so it renders as its own labelled entry rather than folded in.
   */
  loggedMinutes: number
}

/**
 * A phase's own roll-up: the hours worked against the hours estimated summed
 * over its tasks, plus the segnatempo total (spec 0163 D-4). Shared by the
 * list group header and the kanban column. No completion here (user directive
 * 2026-11-16, spec 0401 D-2): it is a column of each task row instead.
 */
export function TaskBoardStageSummary({ metrics, loggedMinutes }: TaskBoardStageSummaryProps) {
  const { t } = useTranslation()
  const hoursLabel = t('workOrders.taskBoard.task.columns.hours')
  const loggedMinutesLabel = t('workOrders.taskBoard.task.columns.loggedMinutes')

  return (
    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
      <span className="flex items-center gap-1.5">
        <span className="text-muted-foreground">{hoursLabel}</span>
        <TaskBoardHours actualMinutes={metrics.actualMinutes} estimatedMinutes={metrics.estimatedMinutes} label={hoursLabel} />
      </span>
      <span className="flex items-center gap-1.5">
        <span className="text-muted-foreground">{loggedMinutesLabel}</span>
        <span className="tabular-nums text-foreground">{formatMinutesLabel(loggedMinutes)}</span>
      </span>
    </div>
  )
}
