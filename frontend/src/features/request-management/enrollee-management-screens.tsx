/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import type { ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'

/**
 * Gestione Iscritti has no creation surface at all (spec 0130 D-8): the
 * registry contract still requires a `FormScreen`, but `mode.type` never
 * reaches `'create'` here — the table renders no "Crea" affordance
 * (`ENROLLEE_MODULE.allowsCreate === false`) and this entry's own
 * `generateRoutes: false` means no `/enrollee-management/new` deep-link
 * exists to reach it either way.
 */
function EnrolleeManagementFormScreen({ onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col items-start gap-3 p-4">
      <p className="text-sm text-muted-foreground">
        {t('enrolleeManagement.form.notApplicable', {
          defaultValue: 'Enrollee Management has no create form: an offer enters here only by changing status.',
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
  FormScreen: EnrolleeManagementFormScreen,
}
