import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { Can } from '@/features/auth/can'
import { Button } from '@/components/ui/button'
import { moduleDefinition, PROJECT_WORK_ORDERS_DOMAIN } from '@/features/work-orders/project-work-order-module'
import { WorkOrdersGrid } from '@/features/work-orders/work-orders-table'

/**
 * "Commesse di Progetto" adapter: same columns, actions and detail as "Commesse" (the backend
 * domain reuses the `work-orders` definition, spec 0359), plus the unread chat column, behind its
 * own `project-work-orders.viewAny` (2026-11-25). No category strip: out of scope for this page.
 * Spec 0431: it also creates a project work order born without an offer.
 */
export function ProjectWorkOrdersTable() {
  const { t } = useTranslation()

  return (
    <WorkOrdersGrid
      domain={PROJECT_WORK_ORDERS_DOMAIN}
      createAction={
        <Can permission="work-orders.create">
          <Button asChild>
            <Link to={`${moduleDefinition.basePath}/new`}>
              <Plus aria-hidden="true" />
              {t('workOrders.form.newStandalone')}
            </Link>
          </Button>
        </Can>
      }
    />
  )
}
