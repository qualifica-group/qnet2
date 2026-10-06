import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FileText, Loader2, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { sumCommissionTotals } from '@/features/quotes/commission-calculator'
import { QuoteStatsStrip } from '@/features/quotes/quote-detail-header'
import { useQuoteLiveTotals } from '@/features/quotes/use-quote-live-totals'
import type { QuoteFormState } from '@/features/quotes/use-quote-form'

interface QuoteFormHeaderProps {
  quoteForm: QuoteFormState
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the quote create form, the detail's own
 * (`QuoteDetailHeader` + `QuoteDetailStats`, spec 0197 D-6): monogram, title
 * and code follow what is being typed, the strip follows the live totals of
 * the rows, and the actions sit where the detail keeps "Scarica preventivo".
 * The status only exists once created. A refused submit is reported right
 * under the actions.
 */
export function QuoteFormHeader({ quoteForm, formId, onCancel }: QuoteFormHeaderProps) {
  const { t } = useTranslation()
  const { form, serverError, vatRatePercentFor } = quoteForm
  const { field: fieldPermission } = useResourcePermissions()
  const [title, code] = useWatch({ control: form.control, name: ['title', 'code'] })
  const { totals, commissionTotals } = useQuoteLiveTotals(form.control, vatRatePercentFor)
  const { isSubmitting } = form.formState
  const heading = title.trim() || t('quotes.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<FileText />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={code.trim() || t('quotes.form.createSubtitle')}
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('quotes.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('quotes.form.saving') : t('quotes.form.save')}
            </Button>
          </>
        }
      />

      {serverError ? (
        <div
          role="alert"
          className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {serverError}
        </div>
      ) : null}

      <QuoteStatsStrip
        revenueNet={totals.revenue.net}
        revenueGross={totals.revenue.gross}
        costNet={totals.cost.net}
        costGross={totals.cost.gross}
        marginNet={totals.margin.net}
        commissionTotal={fieldPermission('commissions').visible ? sumCommissionTotals(commissionTotals) : null}
      />
    </>
  )
}
