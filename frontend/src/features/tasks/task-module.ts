import { OPEN_MODE_PAGE } from '@/features/modules/types'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import type { ModuleDefinition } from '@/features/modules/types'

/** Auto-registered in the module registry (spec 0042). The screens load on first use from `task-screens.tsx`. */
export const moduleDefinition: ModuleDefinition = {
  domain: TASKS_DOMAIN,
  basePath: '/tasks',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.tasks',
  // Spec 0195: the detail IS the edit form — no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own sticky identity bar (heading + actions), so the
  // hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
