import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PurchaseInvoicesTable } from '@/features/purchase-invoices/purchase-invoices-table'

/**
 * Passive invoices page (Accounting > Payable). Light composition only: gates
 * access with `purchase-invoices.view` (the menu permission) and mounts the
 * thin adapter over the generic table (`domain="purchase-invoices"`).
 */
export function PurchaseInvoicesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="purchase-invoices.view"
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseInvoiceViews.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PurchaseInvoicesTable />
      </div>
    </Can>
  )
}

export default PurchaseInvoicesPage
