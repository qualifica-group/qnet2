import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { WorkOrdersTable } from '@/features/work-orders/work-orders-table'

/**
 * Work orders ("Commesse") page. Light composition only: gates access with
 * `work-orders.viewAny` and mounts the thin work-orders adapter, which in
 * turn mounts the generic table (`domain="work-orders"`). The generic table
 * owns config loading and loading/error/empty states; no business logic or
 * data fetching lives here (mirrors `UnitsOfMeasurePage`).
 */
export default function WorkOrdersPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="work-orders.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('workOrders.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <WorkOrdersTable />
      </div>
    </Can>
  )
}
