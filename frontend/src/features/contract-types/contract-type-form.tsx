import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { ContractTypeFormBody } from '@/features/contract-types/contract-type-form-body'
import { CONTRACT_TYPES_DOMAIN, type ContractType } from '@/features/contract-types/types'

interface ContractTypeFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (contractType: ContractType) => void
  onCancel: () => void
}

/**
 * The contract type create form (no edit form: the detail edits in place). Resolves the
 * create-context `ResourcePermissions` (`GET /meta/contract-types`) before rendering the body.
 */
export function ContractTypeForm(props: ContractTypeFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta(CONTRACT_TYPES_DOMAIN)

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
      <ContractTypeFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
