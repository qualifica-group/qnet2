import { useTranslation } from 'react-i18next'
import { LedgerDocumentsTable } from '@/features/ledger/ledger-documents-table'
import { LedgerSummaryPanel } from '@/features/ledger/ledger-summary'
import type { LedgerSection as LedgerSectionData, LedgerSide } from '@/features/ledger/types'

interface LedgerSectionProps {
  side: LedgerSide
  section: LedgerSectionData
  /** Whether the period has a start date, i.e. whether the opening balance row exists. */
  showOpeningBalance: boolean
}

/** One side of the ledger (Cliente or Fornitore): title, summary and documents. */
export function LedgerSection({ side, section, showOpeningBalance }: LedgerSectionProps) {
  const { t } = useTranslation()
  const title = t(`ledger.sections.${side}`)

  return (
    <section aria-label={t('ledger.summary.title', { section: title })} className="flex flex-col gap-3">
      <h3 className="text-base font-semibold">{title}</h3>
      <LedgerSummaryPanel summary={section.summary} side={side} />
      <LedgerDocumentsTable
        documents={section.documents}
        side={side}
        openingBalance={showOpeningBalance ? section.opening_balance : null}
        caption={t('ledger.table.caption', { section: title })}
      />
    </section>
  )
}
