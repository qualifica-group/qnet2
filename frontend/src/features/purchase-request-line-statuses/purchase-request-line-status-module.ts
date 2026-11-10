import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `purchase-request-line-status-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'purchase-request-line-statuses',
  basePath: '/purchase-request-line-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.purchaseRequestLineStatuses',
  // The detail IS the edit surface: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
}
