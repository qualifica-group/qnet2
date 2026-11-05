import { WorkOrdersGrid } from '@/features/work-orders/work-orders-table'

/** Grid domain listing only the `type = project` work orders (spec 0359 D-1). */
export const PROJECT_WORK_ORDERS_DOMAIN = 'project-work-orders'

/**
 * "Commesse di Progetto" adapter: same columns, actions and detail as "Commesse" (the backend
 * domain reuses the `work-orders` definition, spec 0359), plus the unread chat column. No category
 * strip: out of scope for this page.
 */
export function ProjectWorkOrdersTable() {
  return <WorkOrdersGrid domain={PROJECT_WORK_ORDERS_DOMAIN} />
}
