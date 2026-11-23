import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `document-checklist-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'document-checklists',
  basePath: '/document-checklists',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.documentChecklists',
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band, so the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
