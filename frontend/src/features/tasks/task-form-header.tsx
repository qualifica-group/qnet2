import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { CalendarClock, Loader2, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RECORD_HEADER_CLASS } from '@/components/record-form/layout'
import { formatDate } from '@/lib/formatting/date-display'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { TaskDetail } from '@/features/tasks/types'

interface TaskFormHeaderProps {
  control: Control<TaskFormValues>
  /** The persisted task in edit mode, `null` on create. */
  task: TaskDetail | null
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Sticky identity bar of the task form, the same bar the Opportunita' form
 * carries (`RECORD_HEADER_CLASS`): heading and live pills on the left, the
 * actions on the right, a refused submit reported right under them.
 *
 * The status pill shows the PERSISTED status only while the picker still
 * holds it: after a change the picker's own badge names the new one, and a
 * label invented here would be a guess. The due-date pill follows the field live.
 */
export function TaskFormHeader({
  control,
  task,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: TaskFormHeaderProps) {
  const { t } = useTranslation()
  const statusId = useWatch({ control, name: 'task_status_id' })
  const endDate = formatDate(useWatch({ control, name: 'end_date' }))
  const isEdit = task !== null
  const persistedStatus = task && task.task_status.id === statusId ? task.task_status : null

  return (
    <header className={RECORD_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
        <div className="flex min-w-0 flex-col">
          <h1 className="min-w-0 truncate text-base font-semibold">
            {t(isEdit ? 'tasks.form.editTitle' : 'tasks.form.createTitle')}
          </h1>
          <p className="min-w-0 truncate text-sm text-muted-foreground">
            {t(isEdit ? 'tasks.form.editSubtitle' : 'tasks.form.createSubtitle')}
          </p>
        </div>

        {persistedStatus ? (
          <span className="flex min-w-0 items-center gap-1">
            <span className="shrink-0 text-[0.6875rem] uppercase text-muted-foreground">
              {t('tasks.form.header.status')}
            </span>
            <TaskLookupBadge value={persistedStatus} />
          </span>
        ) : null}
        {endDate ? (
          <Badge variant="outline" className="h-5 min-h-5 max-w-full gap-1.5">
            <CalendarClock className="size-3" aria-hidden="true" />
            <span className="text-muted-foreground">{t('tasks.form.header.endDate')}</span>
            <span className="truncate">{endDate}</span>
          </Badge>
        ) : null}
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('tasks.form.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting}>
          {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {isSubmitting ? t('tasks.form.saving') : t('tasks.form.save')}
        </Button>
      </div>

      {submitError ? (
        <div
          role="alert"
          className="flex w-full items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      ) : null}
    </header>
  )
}
