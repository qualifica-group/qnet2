import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `support-topic-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: 'support-topics',
  basePath: '/support-topics',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.supportTopics',
  generateEditRoute: false,
  detailOwnsEditAction: true,
  formOwnsHeader: true,
}
