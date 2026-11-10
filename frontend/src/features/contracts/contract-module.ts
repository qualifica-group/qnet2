import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/**
 * Auto-registered in the module registry (spec 0042). `generateRoutes:
 * false`: contracts has none of the generic `new`/`:id/edit`/`:id/duplicate`
 * routes (D-6) — MT-09 wires only `/contracts` (list) and `/contracts/:id`
 * (the generic `ModuleDetailPage`, by hand) in `router.tsx`, mirroring how
 * every module keeps its list route manual.
 */
export const moduleDefinition: ModuleDefinition = {
  domain: 'contracts',
  basePath: '/contracts',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.contracts',
  generateRoutes: false,
  detailOwnsEditAction: true,
}
