import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Grid and module domain of "Commesse di Progetto": the `type = project` work orders (spec 0359 D-1). */
export const PROJECT_WORK_ORDERS_DOMAIN = 'project-work-orders'

/**
 * "Commesse di Progetto" as a module of its own (user decision 2026-11-25): a
 * project work order opens on `/project-work-orders/:id`, gated by
 * `project-work-orders.view`. Same screens and flags as "Commesse"
 * (`work-order-module.ts`): the record is the same work order.
 */
export const moduleDefinition: ModuleDefinition = {
  domain: PROJECT_WORK_ORDERS_DOMAIN,
  basePath: '/project-work-orders',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.projectWorkOrders',
  generateEditRoute: false,
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}
