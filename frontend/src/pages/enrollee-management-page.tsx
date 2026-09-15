import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import { ENROLLEE_MODULE, RequestModuleProvider } from '@/features/request-management/request-module'

/**
 * Enrollee Management page (spec 0130): identical composition to
 * `RequestManagementPage`, gated by this module's OWN
 * `enrollee-management.viewAny` (D-4), never `request-management.viewAny`.
 * `RequestModuleProvider` is what makes the SHARED `RequestManagementTable`
 * behave as Gestione Iscritti (domain, API paths, permissions, D-8's absent
 * "Crea") — no component is duplicated between the two modules.
 */
export default function EnrolleeManagementPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission={ENROLLEE_MODULE.permission('viewAny')}
      fallback={<p className="text-sm text-muted-foreground">{t('enrolleeManagement.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <RequestModuleProvider module={ENROLLEE_MODULE}>
          <RequestManagementTable />
        </RequestModuleProvider>
      </div>
    </Can>
  )
}
