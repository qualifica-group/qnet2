import { Fragment, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown, ChevronRight } from 'lucide-react'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import { LedgerInstallmentsTable } from '@/features/ledger/ledger-installments-table'
import { LedgerStatusBadge } from '@/features/ledger/ledger-status-badge'
import type { LedgerDocument, LedgerSide } from '@/features/ledger/types'

const HEAD_CELL = 'px-2 py-1.5 text-left text-xs font-medium text-muted-foreground'
const NUMERIC_HEAD_CELL = 'px-2 py-1.5 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1.5 text-sm'
const NUMERIC_CELL = 'px-2 py-1.5 text-right text-sm tabular-nums'
const COLUMN_COUNT = 6

interface LedgerDocumentRowProps {
  document: LedgerDocument
  side: LedgerSide
  expanded: boolean
  onToggle: () => void
}

function documentLabel(document: LedgerDocument, proformaLabel: string, noNumberLabel: string): string {
  if (document.number_label) {
    return document.number_label
  }
  return document.document_type === 'proforma' ? proformaLabel : noNumberLabel
}

function LedgerDocumentRow({ document, side, expanded, onToggle }: LedgerDocumentRowProps) {
  const { t } = useTranslation()
  const label = documentLabel(document, t('ledger.table.proforma'), t('ledger.table.noNumber'))
  const Chevron = expanded ? ChevronDown : ChevronRight

  return (
    <Fragment>
      <tr className="border-t hover:bg-muted/40">
        <td className={CELL}>{formatDate(document.document_date)}</td>
        <td className={CELL}>
          <div className="flex items-center gap-1">
            <button
              type="button"
              aria-expanded={expanded}
              aria-label={t(expanded ? 'ledger.table.collapse' : 'ledger.table.expand', { document: label })}
              onClick={onToggle}
              className="inline-flex size-6 shrink-0 items-center justify-center rounded-md hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
              <Chevron className="size-3.5" aria-hidden="true" />
            </button>
            <span className="flex min-w-0 flex-col">
              <span className="truncate font-medium">{label}</span>
              {document.external_number ? (
                <span className="truncate text-xs text-muted-foreground">
                  {t('ledger.table.external', { number: document.external_number })}
                </span>
              ) : null}
            </span>
          </div>
        </td>
        <td className={NUMERIC_CELL}>{formatEuro(document.debit)}</td>
        <td className={NUMERIC_CELL}>{formatEuro(document.credit)}</td>
        <td className={NUMERIC_CELL}>{formatEuro(document.running_balance)}</td>
        <td className={CELL}>
          <LedgerStatusBadge status={document.status} />
        </td>
      </tr>
      {expanded ? (
        <tr className="bg-muted/40">
          <td colSpan={COLUMN_COUNT} className="px-2 py-2">
            <LedgerInstallmentsTable installments={document.installments} side={side} />
          </td>
        </tr>
      ) : null}
    </Fragment>
  )
}

interface LedgerDocumentsTableProps {
  documents: LedgerDocument[]
  side: LedgerSide
  /** Present only when the period has a start date: the carried-over balance row. */
  openingBalance: number | null
  caption: string
}

/** Documents of one ledger section in date order with running balance and expandable installments (D-11). */
export function LedgerDocumentsTable({ documents, side, openingBalance, caption }: LedgerDocumentsTableProps) {
  const { t } = useTranslation()
  const [expandedIds, setExpandedIds] = useState<ReadonlySet<number>>(new Set())

  const toggle = (id: number) =>
    setExpandedIds((current) => {
      const next = new Set(current)
      if (!next.delete(id)) {
        next.add(id)
      }
      return next
    })

  return (
    <div className="overflow-x-auto rounded-lg border bg-card">
      <table className="w-full min-w-[40rem] border-collapse">
        <caption className="sr-only">{caption}</caption>
        <thead>
          <tr>
            <th scope="col" className={HEAD_CELL}>{t('ledger.table.date')}</th>
            <th scope="col" className={HEAD_CELL}>{t('ledger.table.document')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('ledger.table.debit')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('ledger.table.credit')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('ledger.table.balance')}</th>
            <th scope="col" className={HEAD_CELL}>{t('ledger.table.status')}</th>
          </tr>
        </thead>
        <tbody>
          {openingBalance !== null ? (
            <tr className="border-t bg-muted/40 font-medium">
              <td className={CELL} colSpan={4}>
                {t('ledger.table.openingBalance')}
              </td>
              <td className={NUMERIC_CELL}>{formatEuro(openingBalance)}</td>
              <td className={CELL} />
            </tr>
          ) : null}
          {documents.map((document) => (
            <LedgerDocumentRow
              key={document.id}
              document={document}
              side={side}
              expanded={expandedIds.has(document.id)}
              onToggle={() => toggle(document.id)}
            />
          ))}
          {documents.length === 0 ? (
            <tr className="border-t">
              <td colSpan={COLUMN_COUNT} className="px-2 py-3 text-center text-sm text-muted-foreground">
                {t('ledger.table.empty')}
              </td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </div>
  )
}
