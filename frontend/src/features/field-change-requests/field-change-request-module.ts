import { FIELD_CHANGE_REQUESTS_DOMAIN } from '@/features/field-change-requests/types'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleDefinition } from '@/features/modules/types'

/**
 * Auto-registered in the module registry (spec 0042). `generateRoutes:
 * false`: a request is never created/edited through a route (D-2, the
 * generic proposal dialog is the only entry point) — only `/field-change-
 * requests` (list) and `/field-change-requests/:id` (the generic
 * `ModuleDetailPage`) are wired, by hand, in `router.tsx`, mirroring
 * `contracts`. `detailOwnsEditAction: true` for the same reason `contracts`
 * sets it: there is no edit affordance to gate at all.
 */
export const moduleDefinition: ModuleDefinition = {
  domain: FIELD_CHANGE_REQUESTS_DOMAIN,
  basePath: '/field-change-requests',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.fieldChangeRequests',
  generateRoutes: false,
  detailOwnsEditAction: true,
}
