import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { AvailableInvoiceLine, AvailableLineCommission } from '@/features/invoices/types'

interface InvoiceEditorAvailableLinesProps {
  lines: AvailableInvoiceLine[]
  addedQuoteLineIds: ReadonlySet<number>
  onAdd: (quoteLineId: number) => void
  onAddAll: () => void
}

/** Work-order lines still billable (create only): add one by one or all at once. */
export function InvoiceEditorAvailableLines({ lines, addedQuoteLineIds, onAdd, onAddAll }: InvoiceEditorAvailableLinesProps) {
  const { t } = useTranslation()
  const allAdded = lines.every((line) => addedQuoteLineIds.has(line.quote_line_id))
  const showsCommission = lines.some((line) => line.commission !== null)

  return (
    <section aria-labelledby="invoice-editor-available" className="grid gap-2 rounded-lg border bg-card p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 id="invoice-editor-available" className="text-sm font-semibold">
          {t('invoiceEditor.sections.availableLines')}
        </h3>
        <Button type="button" variant="secondary" size="xs" disabled={lines.length === 0 || allAdded} onClick={onAddAll}>
          {t('invoiceEditor.available.addAll')}
        </Button>
      </div>
      {lines.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('invoiceEditor.available.empty')}</p>
      ) : (
        <div className="max-h-48 overflow-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b text-left text-muted-foreground">
                <th scope="col" className="py-1 pr-2 font-medium">{t('invoiceEditor.available.columns.description')}</th>
                <th scope="col" className="px-2 py-1 text-right font-medium">{t('invoiceEditor.available.columns.quantity')}</th>
                <th scope="col" className="px-2 py-1 text-right font-medium">{t('invoiceEditor.available.columns.unit_price')}</th>
                <th scope="col" className="px-2 py-1 text-right font-medium">{t('invoiceEditor.available.columns.vat_rate')}</th>
                <th scope="col" className="px-2 py-1 text-right font-medium">{t('invoiceEditor.available.columns.total_amount')}</th>
                {showsCommission ? (
                  <th scope="col" className="px-2 py-1 text-right font-medium">{t('invoiceEditor.available.columns.commission')}</th>
                ) : null}
                <th scope="col" className="py-1 pl-2 text-right font-medium">{t('invoiceEditor.available.columns.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => (
                <tr key={line.quote_line_id} className="border-b last:border-0">
                  <td className="max-w-64 truncate py-1 pr-2" title={line.description}>{line.description}</td>
                  <td className="px-2 py-1 text-right tabular-nums">{Number(line.quantity)}</td>
                  <td className="px-2 py-1 text-right tabular-nums">{formatQuoteAmount(Number(line.unit_price))}</td>
                  <td className="px-2 py-1 text-right">{line.vat_rate?.name ?? '-'}</td>
                  <td className="px-2 py-1 text-right tabular-nums">{formatQuoteAmount(Number(line.total_amount))}</td>
                  {showsCommission ? (
                    <td className="px-2 py-1 text-right">{line.commission === null ? '-' : <CommissionCell commission={line.commission} />}</td>
                  ) : null}
                  <td className="py-1 pl-2 text-right">
                    {addedQuoteLineIds.has(line.quote_line_id) ? (
                      <Badge variant="secondary">{t('invoiceEditor.available.added')}</Badge>
                    ) : (
                      <Button type="button" variant="outline" size="xs" aria-label={t('invoiceEditor.available.addLine', { description: line.description })}
                        onClick={() => onAdd(line.quote_line_id)}
                      >
                        {t('invoiceEditor.available.add')}
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

/** The supplier rule behind an institution line amount (spec 0288), or the warning that none applies. */
function CommissionCell({ commission }: { commission: AvailableLineCommission }) {
  const { t } = useTranslation()

  if (commission.type === null) {
    return <Badge variant="destructive">{t('invoiceEditor.available.commission.missing')}</Badge>
  }

  return commission.type === 'PERCENTAGE' ? (
    <span className="tabular-nums">
      {t('invoiceEditor.available.commission.percentage', {
        value: Number(commission.value),
        base: formatQuoteAmount(Number(commission.base_amount)),
      })}
    </span>
  ) : (
    <span>{t('invoiceEditor.available.commission.fixedAmount')}</span>
  )
}
