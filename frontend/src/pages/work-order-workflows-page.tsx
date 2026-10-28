import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { WorkOrderWorkflowsTable } from '@/features/work-order-workflows/work-order-workflows-table'

/**
 * Work order workflow configurator page (spec 0322). Light composition only:
 * gates access with `work-order-workflows.viewAny` and mounts the thin Work Order Workflows adapter,
 * which in turn mounts the generic table (`domain="work-order-workflows"`). The
 * generic table owns config loading and loading/error/empty states; no
 * business logic or data fetching lives here.
 */
export default function WorkOrderWorkflowsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="work-order-workflows.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('workOrderWorkflows.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <WorkOrderWorkflowsTable />
      </div>
    </Can>
  )
}
