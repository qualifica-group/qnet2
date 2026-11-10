import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { SKILLS_DOMAIN } from '@/features/skills/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `skill-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: SKILLS_DOMAIN,
  basePath: `/${SKILLS_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.skills',
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
