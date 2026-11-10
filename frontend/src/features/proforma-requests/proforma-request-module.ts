import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { PROFORMA_REQUESTS_DOMAIN } from '@/features/proforma-requests/api'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `proforma-request-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: PROFORMA_REQUESTS_DOMAIN,
  basePath: '/proforma-requests',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.proformaRequests',
  detailOwnsEditAction: true,
}
