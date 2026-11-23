/**
 * The Completa pop-up's part for a task with open sub-tasks (spec 0418): the
 * notice that they close too and, while the segnatempo is active, the same
 * "Ripartizione" section as the bulk Completa, over the task and its open
 * sub-tasks. Presentational: the data comes from `useTaskCompleteCascade`.
 */
import { useTranslation } from 'react-i18next'
import { TaskBulkTimeEntrySharesSection } from '@/features/tasks/task-bulk-time-entry-shares-section'
import type { useTaskBulkTimeEntryShares } from '@/features/tasks/use-task-bulk-time-entry-shares'
import type { useTaskCompleteCascade } from '@/features/tasks/use-task-complete-cascade'

interface TaskCompleteCascadeSectionProps {
  cascade: ReturnType<typeof useTaskCompleteCascade>
  shares: ReturnType<typeof useTaskBulkTimeEntryShares>
  showShares: boolean
  disabled: boolean
}

export function TaskCompleteCascadeSection({ cascade, shares, showShares, disabled }: TaskCompleteCascadeSectionProps) {
  const { t } = useTranslation()

  return (
    <>
      <p className="text-sm text-muted-foreground">
        {t('tasks.actions.completeDialog.closesSubtasks', { count: cascade.count })}
      </p>
      {cascade.isLoading ? (
        <p className="text-xs text-muted-foreground">{t('tasks.actions.completeDialog.subtasksLoading')}</p>
      ) : null}
      {cascade.isError ? (
        <p className="text-xs text-destructive" role="alert">
          {t('tasks.actions.completeDialog.subtasksError')}
        </p>
      ) : null}
      {showShares && !cascade.isLoading && !cascade.isError ? (
        <TaskBulkTimeEntrySharesSection tasks={cascade.targets} state={shares} disabled={disabled} />
      ) : null}
    </>
  )
}
