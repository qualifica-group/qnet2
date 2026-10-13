import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { CostTypeFormBody } from '@/features/cost-types/cost-type-form-body'
import { COST_TYPES_DOMAIN, type CostTypeDetail } from '@/features/cost-types/types'

interface CostTypeFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (costType: CostTypeDetail) => void
  onCancel: () => void
}

/**
 * The cost type create form (there is no edit form: the detail edits in
 * place). Resolves the create-context `ResourcePermissions`
 * (`GET /meta/cost-types`) before rendering `CostTypeFormBody`.
 */
export function CostTypeForm(props: CostTypeFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta(COST_TYPES_DOMAIN)

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
      <CostTypeFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
