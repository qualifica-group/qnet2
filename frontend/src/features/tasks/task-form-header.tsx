import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { ListChecks, Loader2, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { TaskStatsStrip } from '@/features/tasks/task-detail-header'
import type { TaskFormValues } from '@/features/tasks/task-schema'

interface TaskFormHeaderProps {
  control: Control<TaskFormValues>
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
  /** Derived from the picked status, never a form value; `null` while unpicked. */
  completionPercentage: number | null
}

/**
 * Identity band + KPI strip of the task create form, the detail's own
 * (`TaskDetailHeader` + `TaskDetailStats`, spec 0195 D-8): monogram and title
 * follow what is being typed, the strip follows the picked status, dates and
 * estimate, and the actions sit where the detail keeps its own. A refused
 * submit is reported right under them.
 */
export function TaskFormHeader({
  control,
  formId,
  isSubmitting,
  submitError,
  onCancel,
  completionPercentage,
}: TaskFormHeaderProps) {
  const { t } = useTranslation()
  const [title, startDate, endDate, estimatedMinutes] = useWatch({
    control,
    name: ['title', 'start_date', 'end_date', 'estimated_minutes'],
  })
  const heading = title.trim() || t('tasks.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<ListChecks />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={t('tasks.form.createSubtitle')}
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('tasks.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('tasks.form.saving') : t('tasks.form.save')}
            </Button>
          </>
        }
      />

      {submitError ? (
        <div
          role="alert"
          className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      ) : null}

      <TaskStatsStrip
        completionPercentage={completionPercentage}
        startDate={startDate}
        endDate={endDate}
        estimatedMinutes={estimatedMinutes}
      />
    </>
  )
}
