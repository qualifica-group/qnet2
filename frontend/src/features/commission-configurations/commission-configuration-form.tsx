import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { Button } from '@/components/ui/button'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { CommissionConfigurationFormBody } from './commission-configuration-form-body'
import type { CommissionConfigurationDetail } from './types'

interface CommissionConfigurationFormProps {
  /** Supplier the rule starts on (spec 0204 D-4), from the anagrafica's "Commissioni configurate" tab. */
  supplierRegistryId?: number | null
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (configuration: CommissionConfigurationDetail) => void
  onCancel: () => void
}

/**
 * The commission configuration create form (there is no edit form: the
 * detail edits in place). Metadata-driven (spec 0004): resolves the
 * create-context `ResourcePermissions` before rendering the body.
 */
export function CommissionConfigurationForm({ supplierRegistryId, onSuccess, onCancel }: CommissionConfigurationFormProps) {
  const { t, i18n } = useTranslation()
  const metaQuery = useResourceMeta('commission-configurations')
  // Stable: a fresh object per render would rebuild the form's defaults.
  const mode = useMemo(() => ({ type: 'create' as const, supplierRegistryId }), [supplierRegistryId])

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p role="alert" className="text-sm text-destructive">
          {apiErrorMessage(metaQuery.error, t('authorization.loadError'), i18n)}
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
      <CommissionConfigurationFormBody mode={mode} onSuccess={onSuccess} onCancel={onCancel} />
    </ResourcePermissionsProvider>
  )
}
