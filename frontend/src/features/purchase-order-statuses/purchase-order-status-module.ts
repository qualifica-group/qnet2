import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { PURCHASE_ORDER_STATUSES_RESOURCE } from '@/features/purchase-order-statuses/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `purchase-order-status-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: PURCHASE_ORDER_STATUSES_RESOURCE,
  basePath: `/${PURCHASE_ORDER_STATUSES_RESOURCE}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.purchaseOrderStatuses',
  // The detail IS the edit surface: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
}
