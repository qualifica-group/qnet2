import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `purchase-invoice-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: PURCHASE_INVOICES_DOMAIN,
  basePath: `/${PURCHASE_INVOICES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.purchaseInvoices',
  generateEditRoute: false,
  detailOwnsEditAction: true,
}
