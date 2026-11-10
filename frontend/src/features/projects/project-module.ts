import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `project-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'projects',
  basePath: '/projects',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.projects',
  // The detail IS the edit form (spec 0220): no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
