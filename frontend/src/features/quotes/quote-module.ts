import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `quote-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'quotes',
  basePath: '/quotes',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.quotes',
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
