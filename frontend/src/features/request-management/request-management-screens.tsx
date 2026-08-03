/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { RequestCreateForm } from '@/features/request-management/request-create-form'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'

/**
 * Create-only (spec 0057 D-7): `mode.type === 'create'` mounts the real
 * `RequestCreateForm`; edit/duplicate — unreachable from this module's own
 * table/row actions, but still deep-linkable now that `generateRoutes` is no
 * longer `false` (D-6) — keep answering "not applicable", the pannello di
 * lavorazione stays the only way to change an existing request.
 */
function RequestManagementFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()

  if (mode.type === 'create') {
    return <RequestCreateForm onSuccess={onSuccess} onCancel={onCancel} />
  }

  return (
    <div className="flex flex-col items-start gap-3 p-4">
      <p className="text-sm text-muted-foreground">
        {t('requestManagement.form.notApplicable', {
          defaultValue: 'Request Management has no edit form: work the record from its detail panel.',
        })}
      </p>
      <Button variant="outline" size="sm" onClick={onCancel}>
        {t('common.cancel')}
      </Button>
    </div>
  )
}

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
export const moduleScreen: ModuleRegistryEntry = {
  domain: REQUEST_MANAGEMENT_DOMAIN,
  basePath: '/request-management',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.requestManagement',
  DetailScreen: RequestWorkPanelScreen,
  FormScreen: RequestManagementFormScreen,
  // The create form's sticky bar IS the heading, with the save/cancel actions
  // on its right (user directive 2026-08-03) — the hosts must not render a
  // second one above it.
  formOwnsHeader: true,
}
