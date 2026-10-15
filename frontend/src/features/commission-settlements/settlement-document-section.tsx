import { FileCheck2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { DocumentAmountKey, SettlementDocument } from '@/features/commission-settlements/types'

const AMOUNT_ROWS: DocumentAmountKey[] = ['taxable_amount', 'vat_amount', 'withholding_amount', 'enasarco_amount', 'total_amount']

/** Signed difference (document minus statement): the sign is spelled out so a gap is never conveyed by color alone. */
function formatDifference(value: string): string {
  const amount = Number(value)
  return amount > 0 ? `+${formatEuro(amount)}` : formatEuro(amount)
}

function AmountsTable({ document }: { document: SettlementDocument }) {
  const { t } = useTranslation()
  const { differences } = document
  if (document.taxable_amount === undefined) {
    return null
  }

  return (
    <table aria-label={t('commissionSettlements.document.amountsCaption')} className="mt-2 w-full text-xs">
      <thead>
        <tr className="border-b text-left text-muted-foreground">
          <th scope="col" className="py-1 pr-2 font-medium">{t('commissionSettlements.document.amount')}</th>
          <th scope="col" className="px-2 py-1 text-right font-medium">{t('commissionSettlements.document.declared')}</th>
          <th scope="col" className="py-1 pl-2 text-right font-medium">{t('commissionSettlements.document.difference')}</th>
        </tr>
      </thead>
      <tbody>
        {AMOUNT_ROWS.map((key) => {
          const declared = document[key]
          const difference = differences?.[key]
          const hasGap = difference !== undefined && Number(difference) !== 0
          return (
            <tr key={key} className="border-b last:border-0">
              <th scope="row" className="py-1 pr-2 text-left font-normal">{t(`commissionSettlements.document.${key}`)}</th>
              <td className="px-2 py-1 text-right tabular-nums">{declared === undefined ? '' : formatEuro(declared)}</td>
              <td className={cn('py-1 pl-2 text-right tabular-nums', hasGap && 'font-semibold text-warning')}>
                {difference === undefined ? '' : formatDifference(difference)}
              </td>
            </tr>
          )
        })}
      </tbody>
    </table>
  )
}

/** "Documento agente": what the agent billed, with the signed differences against the statement. */
export function SettlementDocumentSection({ document }: { document: SettlementDocument }) {
  const { t } = useTranslation()
  return (
    <RecordSection title={t('commissionSettlements.document.title')} icon={<FileCheck2 />}>
      <RecordFieldList>
        <RecordField label={t('commissionSettlements.document.number')}>{document.number}</RecordField>
        <RecordField label={t('commissionSettlements.document.date')}>{formatDate(document.date)}</RecordField>
        <RecordField label={t('commissionSettlements.document.receivedAt')}>{formatDateTime(document.received_at)}</RecordField>
        {document.received_by ? (
          <RecordField label={t('commissionSettlements.document.receivedBy')}>{document.received_by.name}</RecordField>
        ) : null}
      </RecordFieldList>
      <AmountsTable document={document} />
    </RecordSection>
  )
}
