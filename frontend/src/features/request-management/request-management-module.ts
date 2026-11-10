import { OPEN_MODE_PAGE } from '@/features/modules/types'
import { REQUEST_MODULE } from '@/features/request-management/request-module'
import type { ModuleDefinition } from '@/features/modules/types'

/**
 * Auto-registered in the module registry (spec 0042). `defaultMode = 'page'`
 * (D-9: the record's own read-only context, contacts and dynamic fields
 * warrant the richer dedicated page over a Sheet). `generateRoutes` no longer
 * pinned to `false` (spec 0057 D-6): the module now HAS a create route
 * (`/new`), so `buildModuleRoutes()` generates the full `new`/`:id`/`:id/edit`/
 * `:id/duplicate` set like every other module. The `:id` deep-link keeps its
 * own bespoke page (`router.tsx`, declared before the generated routes so it
 * takes precedence) — see the comment there for why.
 */
export const moduleDefinition: ModuleDefinition = {
  domain: REQUEST_MODULE.key,
  basePath: REQUEST_MODULE.routeBasePath,
  defaultMode: OPEN_MODE_PAGE,
  labelKey: REQUEST_MODULE.labelKey,
  // The create form's sticky bar IS the heading, with the save/cancel actions
  // on its right (user directive 2026-08-03) — the hosts must not render a
  // second one above it.
  formOwnsHeader: true,
  // User directive 2026-09-28: a created request lands back on the table
  // (whose category tab is persisted), not on the new record's page.
  returnToListOnSave: true,
}
