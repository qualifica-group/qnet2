import { useTranslation } from 'react-i18next'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { Button } from '@/components/ui/button'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { EnasarcoParameterFormBody } from '@/features/enasarco-parameters/enasarco-parameter-form-body'
import { ENASARCO_PARAMETERS_DOMAIN, type EnasarcoParameterDetail } from '@/features/enasarco-parameters/types'

interface EnasarcoParameterFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (parameter: EnasarcoParameterDetail) => void
  onCancel: () => void
}

/**
 * The ENASARCO parameters create form (there is no edit form: the detail
 * edits in place). Resolves the create-context `ResourcePermissions`
 * (`GET /meta/enasarco-parameters`) before rendering the body.
 */
export function EnasarcoParameterForm({ onSuccess, onCancel }: EnasarcoParameterFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta(ENASARCO_PARAMETERS_DOMAIN)

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p role="alert" className="text-sm text-destructive">
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
      <EnasarcoParameterFormBody onSuccess={onSuccess} onCancel={onCancel} />
    </ResourcePermissionsProvider>
  )
}
