import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { HandCoins } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { QuoteCommissionsDialog } from './quote-commissions-dialog'
import type { QuoteLine } from '@/features/quotes/types'

const LINES_GRID_CLASS = 'grid grid-cols-[minmax(200px,1.4fr)_100px_90px_64px_110px_140px_100px_100px_110px_36px] gap-2'

/** Read-only rendering of one tab's persisted rows (D-7: `product.code`/`name` are live, amounts are frozen at save time, D-10). */
function ReadOnlyLine({ line, index, showCommissions }: { line: QuoteLine; index: number; showCommissions: boolean }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  return (
    <div className={`${LINES_GRID_CLASS} border-b px-2 py-1.5 last:border-b-0`}>
      <span className="truncate">{line.product.name}</span>
      <span className="truncate font-mono text-muted-foreground">{line.product.code}</span>
      <span className="tabular-nums">{formatQuoteAmount(Number(line.quantity))}</span>
      <span className="truncate text-muted-foreground">{line.unit_of_measure?.symbol ?? '—'}</span>
      <span className="tabular-nums">{formatQuoteAmount(Number(line.unit_price))}</span>
      <span className="truncate">{line.vat_rate ? line.vat_rate.name : <DetailEmpty />}</span>
      <span className="text-right tabular-nums">{formatQuoteAmount(Number(line.net_amount))}</span>
      <span className="text-right tabular-nums">{formatQuoteAmount(Number(line.vat_amount))}</span>
      <span className="text-right font-medium tabular-nums">{formatQuoteAmount(Number(line.total_amount))}</span>
      {showCommissions ? <><Button type="button" variant="ghost" size="icon-sm" aria-label={t('quotes.form.commissions.action', { n: index + 1 })} onClick={() => setOpen(true)}><HandCoins aria-hidden="true" /></Button>{open ? <QuoteCommissionsDialog open={open} onOpenChange={setOpen} lineNumber={index + 1} productName={line.product.name} productId={line.product_id} quantity={Number(line.quantity)} unitPrice={Number(line.unit_price)} commissions={(line.commissions ?? []).map((commission) => ({ id: commission.id, recipient_role: commission.recipient_role, recipient_type: commission.recipient_type, recipient_id: commission.recipient_id, recipient: commission.recipient, commission_type: commission.commission_type, value: Number(commission.value), internal_note: commission.internal_note, origin: commission.origin, commission_configuration_id: commission.commission_configuration_id }))} disabled onSave={() => undefined} /> : null}</> : null}
    </div>
  )
}

export interface QuoteLinesReadOnlyListProps {
  lines: QuoteLine[]
  showCommissions?: boolean
}

/**
 * Read-only rendering of a quote's product lines, shared between the Offer
 * detail view (spec 0065) and the Contract detail view (spec 0072, BR-7):
 * no edit controls, no addable rows.
 */
export function QuoteLinesReadOnlyList({ lines, showCommissions = false }: QuoteLinesReadOnlyListProps) {
  const { t } = useTranslation()

  if (lines.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('quotes.detail.linesEmpty')}</p>
  }

  return (
    <div className="overflow-x-auto rounded-lg border bg-surface">
      <div className="min-w-[824px] text-xs">
        <div className={`${LINES_GRID_CLASS} border-b bg-muted/40 px-2 py-1.5 font-medium text-muted-foreground`}>
          <span>{t('quotes.form.lineProductHeader')}</span>
          <span>{t('quotes.form.lineCodeHeader')}</span>
          <span>{t('quotes.form.lineQuantityHeader')}</span>
          <span>{t('quotes.form.lineUnitOfMeasureHeader')}</span>
          <span>{t('quotes.form.lineUnitPriceHeader')}</span>
          <span>{t('quotes.form.lineVatRateHeader')}</span>
          <span className="text-right">{t('quotes.form.lineNetHeader')}</span>
          <span className="text-right">{t('quotes.form.lineVatHeader')}</span>
          <span className="text-right">{t('quotes.form.lineTotalHeader')}</span>
          <span className="sr-only">{t('quotes.form.commissions.header')}</span>
        </div>
        {lines.map((line, index) => <ReadOnlyLine key={line.id} line={line} index={index} showCommissions={showCommissions} />)}
      </div>
    </div>
  )
}
