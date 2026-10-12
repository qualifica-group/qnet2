import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PurchaseInvoiceInstallmentsTable } from '@/features/purchase-invoice-installments/purchase-invoice-installments-table'

/**
 * Payable Scadenze page (Accounting > Payable). Light composition only: gates access
 * with `purchase-invoice-installments.view` (the menu permission) and mounts the thin
 * adapter over the generic table (`domain="purchase-invoice-installments"`).
 */
export default function PurchaseInvoiceInstallmentsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="purchase-invoice-installments.view"
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseInvoiceInstallments.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PurchaseInvoiceInstallmentsTable />
      </div>
    </Can>
  )
}
