import { DOCUMENT_LAYOUTS_BASE_PATH } from '@/features/document-layouts/types'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `document-layout-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'document-layouts',
  basePath: DOCUMENT_LAYOUTS_BASE_PATH,
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.documentLayouts',
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
