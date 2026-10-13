import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PurchaseRequestPrioritiesTable } from '@/features/purchase-request-priorities/purchase-request-priorities-table'

/**
 * Purchase request priorities page (spec 0247). Light composition only: gates access with
 * `purchase-request-priorities.viewAny` and mounts the thin adapter, which in turn mounts the
 * generic table (`domain="purchase-request-priorities"`). The generic table owns config loading
 * and loading/error/empty states; no business logic or data fetching lives
 * here (mirrors `WorkOrdersPage`).
 */
export function PurchaseRequestPrioritiesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="purchase-request-priorities.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseRequestPriorities.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PurchaseRequestPrioritiesTable />
      </div>
    </Can>
  )
}

export default PurchaseRequestPrioritiesPage
