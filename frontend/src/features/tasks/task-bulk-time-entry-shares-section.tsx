/**
 * "Ripartizione" of the bulk Completa pop-ups (spec 0338 D-2/D-4): one row per
 * selected task with its editable quota and, when the segnatempo has a start
 * time, its consecutive time slot. Presentational: the state and the rules
 * live in `use-task-bulk-time-entry-shares.ts`.
 */
import type { ChangeEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { TaskShareTarget, useTaskBulkTimeEntryShares } from '@/features/tasks/use-task-bulk-time-entry-shares'

interface TaskBulkTimeEntrySharesSectionProps {
  tasks: TaskShareTarget[]
  state: ReturnType<typeof useTaskBulkTimeEntryShares>
  disabled: boolean
}

export function TaskBulkTimeEntrySharesSection({ tasks, state, disabled }: TaskBulkTimeEntrySharesSectionProps) {
  const { t } = useTranslation()
  const minutesByTask = new Map(state.shares.map((share) => [share.task_id, share.minutes]))
  const slotByTask = new Map((state.slots ?? []).map((slot) => [slot.taskId, slot]))
  const message =
    state.serverError ??
    (state.error === 'too_few_minutes'
      ? t('tasks.bulk.shares.tooFewMinutes')
      : state.error === 'mismatch'
        ? t('tasks.bulk.shares.mismatch', { total: state.total })
        : null)

  return (
    <section className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3" aria-label={t('tasks.bulk.shares.title')}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-sm font-semibold">{t('tasks.bulk.shares.title')}</h3>
        <Button type="button" variant="outline" size="xs" className="bg-card" onClick={state.equalSplit} disabled={disabled}>
          {t('tasks.bulk.shares.equalSplit')}
        </Button>
      </div>
      <p className="text-xs text-muted-foreground">{t('tasks.bulk.shares.hint')}</p>

      <ul className="flex max-h-56 flex-col gap-1.5 overflow-y-auto">
        {tasks.map((task) => {
          const minutes = minutesByTask.get(task.id) ?? 0
          const slot = slotByTask.get(task.id)
          return (
            <li key={task.id} className="flex flex-wrap items-center gap-x-2 gap-y-1">
              <span className="min-w-0 flex-1 basis-40 truncate text-xs" title={task.title}>
                {task.title}
              </span>
              {slot ? (
                <span className="text-xs tabular-nums text-muted-foreground">
                  {t('tasks.bulk.shares.slot', { start: slot.startTime, end: slot.endTime })}
                </span>
              ) : null}
              <Input
                type="number"
                inputMode="numeric"
                min={1}
                className="h-7 w-20 px-2 text-xs"
                aria-label={t('tasks.bulk.shares.minutesFor', { title: task.title })}
                value={minutes > 0 ? minutes : ''}
                disabled={disabled}
                onChange={(event: ChangeEvent<HTMLInputElement>) => state.setMinutes(task.id, event.target.value)}
              />
            </li>
          )
        })}
      </ul>

      <p className="text-xs tabular-nums text-muted-foreground">
        {t('tasks.bulk.shares.assigned', { assigned: state.assigned, total: state.total ?? 0 })}
      </p>
      {message ? (
        <p className="text-xs text-destructive" role="alert">
          {message}
        </p>
      ) : null}
    </section>
  )
}
