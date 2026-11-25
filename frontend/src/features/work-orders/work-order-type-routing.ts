import { moduleDefinition as processingModule } from '@/features/work-orders/work-order-module'
import { moduleDefinition as projectModule } from '@/features/work-orders/project-work-order-module'

/**
 * The module a work order opens in, by type (user decision 2026-11-25):
 * `project` in "Commesse di Progetto", anything else in "Commesse". Mirrors the
 * backend's `WorkOrderTypeAccess`.
 */
export function workOrderModuleFor(type: unknown): { domain: string; basePath: string } {
  return type === 'project' ? projectModule : processingModule
}

/** The page of the work order: `/work-orders/:id` or `/project-work-orders/:id`. */
export function workOrderPath(type: unknown, id: number | string): string {
  return `${workOrderModuleFor(type).basePath}/${id}`
}

/**
 * Where a work order page reached on the other module's path must go, keeping
 * the query (`?tab=`); `null` when `pathname` is not that path (already right,
 * or not a page at all: the record opened in a Sheet).
 */
export function workOrderRedirectPath(type: unknown, id: number, pathname: string, search: string): string | null {
  const target = workOrderPath(type, id)
  const other = workOrderPath(type === 'project' ? 'processing' : 'project', id)

  return pathname === other ? `${target}${search}` : null
}
