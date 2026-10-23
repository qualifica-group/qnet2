import { useState } from 'react'
import { RefreshCw } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { ControlledChoiceDialog } from '@/components/controlled-choice-dialog'
import { Button } from '@/components/ui/button'
import type { CommissionRecalculationMode } from '@/features/commission-configurations/types'
import { useQuoteCommissionRecalculation } from '@/features/quotes/use-quote-commission-recalculation'

interface QuoteRecalculateCommissionsProps {
  quoteId: number
  onChanged?: () => void
}

/**
 * "Ricalcola commissioni" (spec 0308 D-4): realigns the open quote to the
 * current rules. The user must pick how the manual commissions are treated;
 * nothing is preselected. The caller gates it on the `recalculate_commissions` action.
 */
export function QuoteRecalculateCommissions({ quoteId, onChanged }: QuoteRecalculateCommissionsProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const { impact, recalculate } = useQuoteCommissionRecalculation(quoteId, open, () => {
    setOpen(false)
    onChanged?.()
  })
  const manual = impact.data?.manual_commissions_count ?? 0

  return (
    <>
      <Button type="button" variant="secondary" size="sm" onClick={() => setOpen(true)}>
        <RefreshCw aria-hidden="true" />
        {t('quotes.recalculateCommissions.button')}
      </Button>
      <ControlledChoiceDialog<CommissionRecalculationMode>
        open={open}
        dismissible
        title={t('quotes.recalculateCommissions.title')}
        description={t('quotes.recalculateCommissions.description')}
        summary={
          impact.data ? (
            t('quotes.recalculateCommissions.summary', {
              lines: impact.data.lines_count,
              commissions: impact.data.commissions_count,
              manual: impact.data.manual_commissions_count,
            })
          ) : impact.isError ? (
            t('quotes.recalculateCommissions.impactError')
          ) : (
            t('common.loading')
          )
        }
        choiceLabel={t('quotes.recalculateCommissions.choiceLabel')}
        options={[
          { value: 'KEEP_MANUAL', label: t('quotes.recalculateCommissions.keepManual', { count: manual }) },
          { value: 'REGENERATE_ALL', label: t('quotes.recalculateCommissions.regenerateAll', { count: manual }) },
        ]}
        confirmLabel={t('quotes.recalculateCommissions.confirm')}
        pending={recalculate.isPending || !impact.data}
        onConfirm={(mode) => recalculate.mutate(mode)}
        onOpenChange={setOpen}
      />
    </>
  )
}
