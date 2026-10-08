import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { WorkOrderPaymentStatusesTable } from '@/features/work-order-payment-statuses/work-order-payment-statuses-table'

/**
 * Work order payment statuses page. Light composition only: gates access with
 * `work-order-payment-statuses.viewAny` and mounts the thin adapter, which in
 * turn mounts the generic table (`domain="work-order-payment-statuses"`).
 */
export default function WorkOrderPaymentStatusesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="work-order-payment-statuses.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">{t('workOrderPaymentStatuses.forbidden')}</p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <WorkOrderPaymentStatusesTable />
      </div>
    </Can>
  )
}
