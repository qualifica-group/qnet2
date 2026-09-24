/**
 * The labelled building blocks of a task row (user directive 2026-09-22: every
 * value must say what it is). `TaskBoardMetaField` is one `dt`/`dd` pair of the
 * row's definition list; `TaskBoardPeople` shows each person as the app's
 * shared avatar + `UserProfileHoverCard` (the same affordance as the detail
 * pages: hover reveals the profile action, click opens the user Sheet);
 * `TaskBoardHours` turns worked-vs-estimated minutes into a bar plus its
 * number; completion uses the shared `CompletionBar`.
 */

import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { CompletionBar } from '@/components/completion-bar'
import { Progress } from '@/components/ui/progress'
import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import type { TaskNamedRef } from '@/features/tasks/types'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import type { TaskBoardStageMetrics } from '@/features/work-orders/task-board/task-board-metrics'
import { cn } from '@/lib/utils'

const PERCENT_MAX = 100
const EMPTY_VALUE = '—'
/** People beyond the first shown as bare avatars before the rest collapses into "+N". */
const EXTRA_AVATARS_MAX = 2

export function TaskBoardMetaField({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex min-w-0 flex-col gap-0.5">
      <dt className="text-[10px] leading-tight font-medium tracking-wide text-muted-foreground uppercase">{label}</dt>
      <dd className="flex min-h-5 min-w-0 items-center gap-1.5 text-xs text-foreground">{children}</dd>
    </div>
  )
}

export function TaskBoardEmptyValue() {
  return <span className="text-muted-foreground">{EMPTY_VALUE}</span>
}

export function TaskBoardPeople({ people }: { people: TaskNamedRef[] }) {
  const { t } = useTranslation()

  if (people.length === 0) {
    return <span className="text-muted-foreground">{t('workOrders.taskBoard.task.nobody')}</span>
  }

  const [first, ...others] = people
  const extraAvatars = others.slice(0, EXTRA_AVATARS_MAX)
  const hidden = others.slice(EXTRA_AVATARS_MAX)

  return (
    <>
      <UserProfileHoverCard user={first} triggerClassName="min-w-0 gap-1.5 rounded-md">
        <UserAvatar name={first.name} size="sm" className="shrink-0" />
        <span className="truncate text-xs text-foreground">{first.name}</span>
      </UserProfileHoverCard>
      {extraAvatars.map((person) => (
        <UserProfileHoverCard key={person.id} user={person} triggerClassName="shrink-0 rounded-full">
          <UserAvatar name={person.name} size="sm" />
        </UserProfileHoverCard>
      ))}
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

/**
 * A phase's own roll-up (user directive 2026-09-22): completion as the mean
 * of its tasks' completion, and the hours worked against the hours estimated
 * summed over its tasks. Shared by the list group header and the kanban column.
 */
export function TaskBoardStageSummary({ metrics }: { metrics: TaskBoardStageMetrics }) {
  const { t } = useTranslation()
  const completionLabel = t('workOrders.taskBoard.task.columns.completion')
  const hoursLabel = t('workOrders.taskBoard.task.columns.hours')

  return (
    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
      <span className="flex items-center gap-1.5">
        <span className="text-muted-foreground">{completionLabel}</span>
        <CompletionBar value={metrics.completionPercentage} label={completionLabel} barClassName="w-14" className="gap-1.5" />
      </span>
      <span className="flex items-center gap-1.5">
        <span className="text-muted-foreground">{hoursLabel}</span>
        <TaskBoardHours actualMinutes={metrics.actualMinutes} estimatedMinutes={metrics.estimatedMinutes} label={hoursLabel} />
      </span>
    </div>
  )
}
