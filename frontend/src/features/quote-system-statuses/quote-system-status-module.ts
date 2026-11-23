import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `quote-system-status-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'quote-system-statuses',
  basePath: '/quote-system-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.quoteSystemStatuses',
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}
