import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { UnitOfMeasureFormBody } from '@/features/units-of-measure/unit-of-measure-form-body'
import type { UnitOfMeasureDetail } from '@/features/units-of-measure/types'

interface UnitOfMeasureFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (unitOfMeasure: UnitOfMeasureDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * The unit of measure create form (there is no edit form: the detail edits
 * in place). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/units-of-measure`) before rendering
 * `UnitOfMeasureFormBody`.
 */
export function UnitOfMeasureForm(props: UnitOfMeasureFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('units-of-measure')

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
      <UnitOfMeasureFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
