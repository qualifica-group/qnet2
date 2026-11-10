import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `product-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'products',
  basePath: '/products',
  defaultMode: OPEN_MODE_PAGE,
  generateRoutes: false,
  labelKey: 'navigation.products',
  // The detail IS the edit form (spec 0218): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
