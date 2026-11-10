/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import type { ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'
import { RequestCreateForm } from '@/features/request-management/request-create-form'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'

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

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: RequestWorkPanelScreen,
  FormScreen: RequestManagementFormScreen,
}
