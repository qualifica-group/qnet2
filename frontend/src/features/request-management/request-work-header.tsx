import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { CalendarClock, Loader2, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatDateTimeOptionalTime } from '@/features/table/cell-renderers'
import { WorkflowStatusSwatch } from '@/features/request-management/request-workflow-status-field'
import type { RequestWorkPanel } from '@/features/request-management/types'

interface RequestWorkHeaderProps {
  panel: RequestWorkPanel
  canUpdate: boolean
  /** id of the RHF `<form>` the Save button attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  isDirty: boolean
  /** Why the last submit did not go through (validation summary or server error); `null` when there is none. */
  submitError: string | null
}

/** A compact status pill: micro-label + swatch + name, so state never reads from color alone. */
function StatusBadge({ label, color, children }: { label: string; color: string | null; children: ReactNode }) {
  return (
    <Badge variant="secondary" className="h-5 min-h-5 max-w-full gap-1.5">
      <span className="text-muted-foreground">{label}</span>
      <WorkflowStatusSwatch color={color} />
      <span className="truncate">{children}</span>
    </Badge>
  )
}

/**
 * Identity bar of the work panel: who the record is, its two statuses and the
 * scheduled callback on the left, the page's ONLY save action on the right.
 * Sticky so the primary action stays reachable while the operator scrolls the
 * long editable form below; the button submits the form by id, keeping this
 * component free of any form state.
 *
 * A refused submit is reported HERE, next to the button that was pressed: the
 * form below is long and some of its blocking fields render no message of
 * their own, so an error shown only in place reads as "the save button does
 * nothing".
 */
export function RequestWorkHeader({
  panel,
  canUpdate,
  formId,
  isSubmitting,
  isDirty,
  submitError,
}: RequestWorkHeaderProps) {
  const { t } = useTranslation()
  // The callback hour is optional (user directive 2026-07-31): one planned
  // without it reads as a plain date here too, never as "00:00".
  const nextCallback = formatDateTimeOptionalTime(panel.next_callback_at)

  return (
    <header className="sticky top-0 z-20 flex flex-wrap items-center gap-x-3 gap-y-2 border-b bg-card/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-card/80">
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
        <h1 className="min-w-0 max-w-full truncate text-base font-semibold">
          {t('requestManagement.workPanel.header.title', { defaultValue: 'Preliminary information' })}
        </h1>
        <span className="shrink-0 text-xs tabular-nums text-muted-foreground">#{panel.id}</span>

        {panel.opportunity_status && (
          <StatusBadge
            label={t('requestManagement.workPanel.header.salesStatus', { defaultValue: 'Sales' })}
            color={panel.opportunity_status.color}
          >
            {panel.opportunity_status.name}
          </StatusBadge>
        )}
        {panel.workflow_status && (
          <StatusBadge
            label={t('requestManagement.workPanel.header.workingStatus', { defaultValue: 'Working' })}
            color={panel.workflow_status.color}
          >
            {panel.workflow_status.name}
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
        {isDirty && (
          <span className="text-xs text-muted-foreground">
            {t('requestManagement.workPanel.header.unsavedChanges', { defaultValue: 'Unsaved changes' })}
          </span>
        )}
        {canUpdate && (
          <Button type="submit" form={formId} disabled={isSubmitting || !isDirty}>
            {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
            {isSubmitting
              ? t('requestManagement.workPanel.saving')
              : t('requestManagement.workPanel.save')}
          </Button>
        )}
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
