import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `lead-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'leads',
  basePath: '/leads',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.leads',
  // The detail IS the edit form (spec 0217): no edit route, no Edit button.
  // The record card renders the lead -> opportunity CTA itself.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
