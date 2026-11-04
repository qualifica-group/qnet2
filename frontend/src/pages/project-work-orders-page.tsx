import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { ProjectWorkOrdersTable } from '@/features/work-orders/project-work-orders-table'

/**
 * Project work orders ("Commesse di Progetto") page. Light composition only: same
 * `work-orders.viewAny` gate as "Commesse", then the thin adapter over the generic table.
 */
export default function ProjectWorkOrdersPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="work-orders.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('workOrders.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <ProjectWorkOrdersTable />
      </div>
    </Can>
  )
}
