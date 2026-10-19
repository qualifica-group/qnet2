import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { AbsenceTypeFormBody } from '@/features/absence-types/absence-type-form-body'
import { ABSENCE_TYPES_DOMAIN, type AbsenceType } from '@/features/absence-types/types'

interface AbsenceTypeFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (absenceType: AbsenceType) => void
  onCancel: () => void
}

/**
 * The absence type create form (no edit form: the detail edits in place). Resolves the
 * create-context `ResourcePermissions` (`GET /meta/absence-types`) before rendering the body.
 */
export function AbsenceTypeForm(props: AbsenceTypeFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta(ABSENCE_TYPES_DOMAIN)

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <AbsenceTypeFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
