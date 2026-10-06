import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Hammer, Loader2, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { WorkOrderStatsStrip } from '@/features/work-orders/work-order-detail-header'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'

interface WorkOrderFormHeaderProps {
  control: Control<WorkOrderFormValues>
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the work order create form, the detail's own
 * (`WorkOrderDetailHeader` + `WorkOrderDetailStats`, spec 0195 D-8 applied to
 * Commesse): monogram, title and code follow what is being typed, the strip
 * follows the dates, and the actions sit where the detail keeps its badges.
 * Completion and contract number only exist once created. A refused submit is
 * reported right under the actions.
 */
export function WorkOrderFormHeader({ control, formId, isSubmitting, submitError, onCancel }: WorkOrderFormHeaderProps) {
  const { t } = useTranslation()
  const [title, code, startDate, callbackDate] = useWatch({
    control,
    name: ['title', 'code', 'start_date', 'callback_date'],
  })
  const heading = title.trim() || t('workOrders.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<Hammer />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={code.trim() || t('workOrders.form.createSubtitle')}
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('workOrders.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('workOrders.form.saving') : t('workOrders.form.save')}
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

      <WorkOrderStatsStrip
        completionPercentage={null}
        startDate={startDate || null}
        callbackDate={callbackDate}
        contractNumber={null}
      />
    </>
  )
}
