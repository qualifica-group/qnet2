import { PROJECT_WORK_ORDERS_DOMAIN } from '@/features/work-orders/project-work-order-module'
import { WorkOrdersGrid } from '@/features/work-orders/work-orders-table'

/**
 * "Commesse di Progetto" adapter: same columns, actions and detail as "Commesse" (the backend
 * domain reuses the `work-orders` definition, spec 0359), plus the unread chat column, behind its
 * own `project-work-orders.viewAny` (2026-11-25). No category strip: out of scope for this page.
 */
export function ProjectWorkOrdersTable() {
  return <WorkOrdersGrid domain={PROJECT_WORK_ORDERS_DOMAIN} />
}
