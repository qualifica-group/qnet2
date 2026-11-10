import { COMPANY_CLOSURES_DOMAIN } from '@/features/company-closures/types'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `company-closure-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: COMPANY_CLOSURES_DOMAIN,
  basePath: `/${COMPANY_CLOSURES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.companyClosures',
  // The form renders its own identity band (title + actions on one row).
  formOwnsHeader: true,
}
