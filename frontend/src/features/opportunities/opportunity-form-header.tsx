import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { CalendarClock, Loader2, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RECORD_HEADER_CLASS } from '@/components/record-form/layout'
import { formatDate } from '@/lib/formatting/date-display'
import { OpportunityStatusBadge } from '@/features/opportunities/opportunity-status-badge'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type {
  OpportunityStatusSummary,
  OpportunityWorkflowStatusRef,
} from '@/features/opportunities/types'

/**
 * The computed summary with the working state the form currently holds
 * substituted in — but ONLY on the `workflow` source, where that state is
 * literally what the badge displays (spec 0082: no quote yet). On the `quotes`
 * source the summary is derived from the offers and the select below has no
 * say in it, so it is returned untouched.
 */
function mergeWorkingStatus(
  status: OpportunityStatusSummary | null,
  statuses: OpportunityWorkflowStatusRef[] | null,
  selectedId: number | null,
): OpportunityStatusSummary | null {
  if (status === null || status.source !== 'workflow') {
    return status
  }

  const selected = statuses?.find((candidate) => candidate.id === selectedId) ?? null

  if (selected === null) {
    return status
  }

  return {
    ...status,
    distinct_count: 1,
    entries: [{ id: selected.id, name: selected.name, color: selected.color, group: selected.group, count: 0 }],
  }
}

interface OpportunityFormHeaderProps {
  control: Control<OpportunityFormValues>
  /** Drives the heading pair and which pills can exist at all (a computed status needs a saved record). */
  isEdit: boolean
  /** Spec 0082: the COMPUTED status of the loaded opportunity; `null` in create (no quote exists yet). */
  status: OpportunityStatusSummary | null
  /** The resolved working-state set, `null` in create mode (not yet known): the pill names the picked row from it. */
  workflowStatuses: OpportunityWorkflowStatusRef[] | null
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  /** BR-1/D-2: the picked lead already has an opportunity, so the submit is refused before it is attempted. */
  isSubmitDisabled: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity bar of the opportunity form — the same bar the Gestione Richieste
 * screens carry (`RECORD_HEADER_CLASS`), user directive 2026-08-05: heading
 * and live status pills on the left, the actions on the right, a refused
 * submit reported right under the button that was pressed.
 *
 * This bar is the form's ONE heading: the module is registered
 * `formOwnsHeader`, so the dedicated page drops its own title/subtitle block
 * and the Sheet keeps its `SheetHeader` `sr-only`. It reuses the hosts' own
 * `form.createTitle`/`editTitle` strings — same text as before, now on the
 * same row as the save/cancel actions instead of stacked above them.
 *
 * ONE status pill, not two (user directive 2026-08-05: "mergiali come
 * mergiati sulla tabella"): the COMPUTED status (spec 0082) drawn by the very
 * badge the grid cell uses, which already merges the two dimensions — it falls
 * back to the working state when the opportunity has no quote yet, and
 * collapses N distinct quote statuses into the neutral "N stati" badge.
 *
 * Kept LIVE against the select below it: on the `workflow` source what the
 * badge shows IS the working state, so picking another one re-labels the pill
 * immediately instead of contradicting the field until the next save. On the
 * `quotes` source the working state is not what is displayed, so the computed
 * summary stands untouched.
 */
export function OpportunityFormHeader({
  control,
  isEdit,
  status,
  workflowStatuses,
  formId,
  isSubmitting,
  isSubmitDisabled,
  submitError,
  onCancel,
}: OpportunityFormHeaderProps) {
  const { t } = useTranslation()
  const workflowStatusId = useWatch({ control, name: 'opportunity_workflow_status_id' })
  const expectedCloseDate = useWatch({ control, name: 'expected_close_date' })

  const closeDate = formatDate(expectedCloseDate)
  const liveStatus = mergeWorkingStatus(status, workflowStatuses, workflowStatusId)

  return (
    <header className={RECORD_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
        <div className="flex min-w-0 flex-col">
          <h1 className="min-w-0 truncate text-base font-semibold">
            {t(isEdit ? 'opportunities.form.editTitle' : 'opportunities.form.createTitle')}
          </h1>
          <p className="min-w-0 truncate text-sm text-muted-foreground">
            {t(isEdit ? 'opportunities.form.editSubtitle' : 'opportunities.form.createSubtitle')}
          </p>
        </div>

        {liveStatus && liveStatus.entries.length > 0 && (
          <span className="flex min-w-0 items-center gap-1">
            <span className="shrink-0 text-[0.6875rem] uppercase text-muted-foreground">
              {t('opportunities.form.header.status')}
            </span>
            <OpportunityStatusBadge summary={liveStatus} />
          </span>
        )}
        {closeDate && (
          <Badge variant="outline" className="h-5 min-h-5 max-w-full gap-1.5">
            <CalendarClock className="size-3" aria-hidden="true" />
            <span className="text-muted-foreground">{t('opportunities.form.header.expectedCloseDate')}</span>
            <span className="truncate">{closeDate}</span>
          </Badge>
        )}
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('opportunities.form.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting || isSubmitDisabled}>
          {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
          {isSubmitting ? t('opportunities.form.saving') : t('opportunities.form.save')}
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
