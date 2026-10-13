import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PurchaseRequestLineStatusesTable } from '@/features/purchase-request-line-statuses/purchase-request-line-statuses-table'

/**
 * Purchase request line statuses page (spec 0248). Light composition only:
 * gates access with `purchase-request-line-statuses.viewAny` and mounts the
 * thin adapter, which mounts the generic table.
 */
export function PurchaseRequestLineStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="purchase-request-line-statuses.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('purchaseRequestLineStatuses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <PurchaseRequestLineStatusesTable />
      </div>
    </Can>
  )
}

export default PurchaseRequestLineStatusesPage
