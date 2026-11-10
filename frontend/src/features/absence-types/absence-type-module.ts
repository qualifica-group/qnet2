import { ABSENCE_TYPES_DOMAIN } from '@/features/absence-types/types'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `absence-type-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: ABSENCE_TYPES_DOMAIN,
  basePath: `/${ABSENCE_TYPES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.absenceTypes',
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
