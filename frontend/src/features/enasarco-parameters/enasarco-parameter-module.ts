import { ENASARCO_PARAMETERS_DOMAIN } from '@/features/enasarco-parameters/types'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `enasarco-parameter-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: ENASARCO_PARAMETERS_DOMAIN,
  basePath: `/${ENASARCO_PARAMETERS_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.enasarcoParameters',
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band, so the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
