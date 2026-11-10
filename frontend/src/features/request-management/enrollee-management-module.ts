import { ENROLLEE_MODULE } from '@/features/request-management/request-module'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/**
 * Auto-registered in the module registry (spec 0042), same mechanism as
 * `request-management-screens.tsx`. `DetailScreen` is the literal SAME
 * `RequestWorkPanelScreen` component — it reads the active module from
 * `useRequestModule()`, resolved from context, never hard-coded — so no
 * behaviour is duplicated between the two modules (spec 0130 constraint: no
 * copied files/components).
 */
export const moduleDefinition: ModuleDefinition = {
  domain: ENROLLEE_MODULE.key,
  basePath: ENROLLEE_MODULE.routeBasePath,
  defaultMode: OPEN_MODE_PAGE,
  labelKey: ENROLLEE_MODULE.labelKey,
  generateRoutes: false,
}
