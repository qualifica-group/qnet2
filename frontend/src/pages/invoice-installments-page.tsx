import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { InvoiceInstallmentsTable } from '@/features/invoice-installments/invoice-installments-table'

/**
 * Scadenze page (Accounting > Receivables). Light composition only: gates access
 * with `invoice-installments.view` (the menu permission) and mounts the thin
 * adapter over the generic table (`domain="invoice-installments"`).
 */
export default function InvoiceInstallmentsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="invoice-installments.view"
      fallback={<p className="text-sm text-muted-foreground">{t('invoiceInstallments.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <InvoiceInstallmentsTable />
      </div>
    </Can>
  )
}
