import { useTranslation } from 'react-i18next'
import { InvoiceInstallmentsSection } from '@/features/invoices/invoice-installments-section'
import { InvoiceLinesTable } from '@/features/invoices/invoice-lines-table'
import type { InvoiceWithPermissions } from '@/features/invoices/types'

interface InvoiceDocumentSectionsProps {
  invoice: InvoiceWithPermissions
  /** Called after a collection is recorded/cleared (the caller refreshes its grid). */
  onChanged?: () => void
}

/** "Righe" + "Scadenze" of a document, as the grid's expanded row shows them. */
export function InvoiceDocumentSections({ invoice, onChanged }: InvoiceDocumentSectionsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-3">
      <section className="flex flex-col gap-1.5">
        <h3 className="text-xs font-semibold text-foreground">{t('invoices.detail.lines')}</h3>
        <InvoiceLinesTable lines={invoice.lines} />
      </section>
      <InvoiceInstallmentsSection invoice={invoice} onChanged={onChanged} />
    </div>
  )
}
