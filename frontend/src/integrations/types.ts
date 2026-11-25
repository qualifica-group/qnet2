import type { ComponentType } from 'react'
import type { RouteObject } from 'react-router-dom'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import type { WorkOrderDetailTab } from '@/features/work-orders/use-work-order-detail-tabs'

export interface IntegrationWorkOrderTabsArgs {
  workOrder: WorkOrderDetailWithPermissions
  /** False when the integration is not active: the hook must still run, but fetch nothing and return no tab. */
  enabled: boolean
}

/**
 * A client-specific vertical plugged into the core through generic extension
 * points only (spec 0422 D-1). Its i18n namespace is picked up by
 * `src/i18n/index.ts` from `<integration>/i18n/index.ts`.
 */
export interface IntegrationDefinition {
  /** Key the backend lists in `GET /api/integrations` when the integration is active. */
  key: string
  /** Extra tabs of the work order detail; called unconditionally on every render. */
  useWorkOrderTabs?: (args: IntegrationWorkOrderTabsArgs) => WorkOrderDetailTab[]
  /** Extra actions on top of the work order's Amministrazione > Costi, rendered only while the integration is active. */
  WorkOrderAccountingActions?: ComponentType<{ workOrder: WorkOrderDetailWithPermissions }>
  /** Extra full-width section of the product category detail, rendered only while the integration is active. */
  ProductCategorySection?: ComponentType<{ categoryId: number }>
  /** Extra routes of the "Develop" menu section. */
  devRoutes?: RouteObject[]
}

/** Shape of `<integration>/i18n/index.ts`'s default export. */
export interface IntegrationI18nResources {
  namespace: string
  it: Record<string, unknown>
  en: Record<string, unknown>
}
