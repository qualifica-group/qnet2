import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { FieldChangeRequestsTable } from '@/features/field-change-requests/field-change-requests-table'

/**
 * Field change requests page (spec 0078). Light composition only: gates
 * access with `field-change-requests.viewAny` and mounts the thin adapter,
 * which in turn mounts the generic table (`domain="field-change-requests"`).
 * No business logic or data fetching lives here.
 */
export default function FieldChangeRequestsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="field-change-requests.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">{t('fieldChangeRequests.forbidden')}</p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <FieldChangeRequestsTable />
      </div>
    </Can>
  )
}
