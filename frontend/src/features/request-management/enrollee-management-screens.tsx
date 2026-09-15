/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { ENROLLEE_MODULE } from '@/features/request-management/request-module'

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

/**
 * Auto-registered in the module registry (spec 0042), same mechanism as
 * `request-management-screens.tsx`. `DetailScreen` is the literal SAME
 * `RequestWorkPanelScreen` component — it reads the active module from
 * `useRequestModule()`, resolved from context, never hard-coded — so no
 * behaviour is duplicated between the two modules (spec 0130 constraint: no
 * copied files/components).
 */
export const moduleScreen: ModuleRegistryEntry = {
  domain: ENROLLEE_MODULE.key,
  basePath: ENROLLEE_MODULE.routeBasePath,
  defaultMode: OPEN_MODE_PAGE,
  labelKey: ENROLLEE_MODULE.labelKey,
  generateRoutes: false,
  DetailScreen: RequestWorkPanelScreen,
  FormScreen: EnrolleeManagementFormScreen,
}
