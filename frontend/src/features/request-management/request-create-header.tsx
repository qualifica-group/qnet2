import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { CalendarClock, Loader2, TriangleAlert } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatDateTimeOptionalTime } from '@/features/table/cell-renderers'
import { REQUEST_HEADER_CLASS, StatusBadge } from '@/features/request-management/request-work-header'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import type { RequestWorkflowStatusRef } from '@/features/request-management/types'

interface RequestCreateHeaderProps {
  control: Control<RequestCreateFormValues>
  /** The set resolved for the criteria typed so far — the badge names the picked row from it. */
  statuses: RequestWorkflowStatusRef[]
  /** id of the RHF `<form>` the save button attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: ReactNode
  onCancel: () => void
}

/**
 * Identity bar of the create form — the same bar as the work panel's
 * (`RequestWorkHeader`), down to the shared `REQUEST_HEADER_CLASS` and the
 * shared `StatusBadge`: heading and live status/callback pills on the left,
 * the actions on the right, a refused submit reported right under the button
 * that was pressed.
 *
 * This bar is the form's ONE heading (user directive 2026-08-03): the module
 * is registered `formOwnsHeader`, so the dedicated page drops its own
 * title/subtitle block and the Sheet keeps its `SheetHeader` `sr-only`. It
 * reuses the hosts' own `form.createTitle`/`createSubtitle` strings — same
 * text as before, now on the same row as the save/cancel actions instead of
 * stacked above a second copy of itself.
 *
 * Two pills of the panel are missing here for a reason, not by omission: `#id`
 * and the "Commerciale" (sales pipeline) status do not exist until the record
 * does. The two that CAN be known while filling the form — the working status
 * and the planned callback — render live, exactly as the panel renders its
 * persisted ones.
 */
export function RequestCreateHeader({
  control,
  statuses,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: RequestCreateHeaderProps) {
  const { t } = useTranslation()
  const statusId = useWatch({ control, name: 'opportunity_workflow_status_id' })
  const nextCallbackAt = useWatch({ control, name: 'next_callback_at' })

  const status = statuses.find((candidate) => candidate.id === statusId) ?? null
  const nextCallback = formatDateTimeOptionalTime(nextCallbackAt)

  return (
    <header className={REQUEST_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
        <div className="flex min-w-0 flex-col">
          <h1 className="min-w-0 truncate text-base font-semibold">
            {t('requestManagement.form.createTitle')}
          </h1>
          <p className="min-w-0 truncate text-sm text-muted-foreground">
            {t('requestManagement.form.createSubtitle')}
          </p>
        </div>

        {status && (
          <StatusBadge
            label={t('requestManagement.workPanel.header.workingStatus', { defaultValue: 'Working' })}
            color={status.color}
          >
            {status.name}
          </StatusBadge>
        )}
        {nextCallback && (
          <Badge variant="outline" className="h-5 min-h-5 max-w-full gap-1.5">
            <CalendarClock className="size-3" aria-hidden="true" />
            <span className="text-muted-foreground">
              {t('requestManagement.workPanel.header.nextCallback', { defaultValue: 'Next callback' })}
            </span>
            <span className="truncate">{nextCallback}</span>
          </Badge>
        )}
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('requestManagement.form.create.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting}>
          {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
          {isSubmitting ? t('requestManagement.form.create.saving') : t('requestManagement.form.create.save')}
        </Button>
      </div>

      {submitError && (
        <div
          role="alert"
          className="flex w-full items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      )}
    </header>
  )
}
