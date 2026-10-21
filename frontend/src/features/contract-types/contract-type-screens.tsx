/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { contractTypeKeys, fetchContractType } from '@/features/contract-types/api'
import { ContractTypeForm } from '@/features/contract-types/contract-type-form'
import { ContractTypeDetailView } from '@/features/contract-types/contract-type-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { CONTRACT_TYPES_DOMAIN, type ContractType } from '@/features/contract-types/types'

/** Content-only `contract-types` screens for the module registry (spec 0042). */
export function ContractTypeDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: contractType,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(contractTypeKeys.detail(id), () => fetchContractType(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('contractTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !contractType) {
    return <DetailLoading />
  }

  return <ContractTypeDetailView contractType={contractType} onChanged={onChanged} />
}

/** Create only: there is no edit form, and the registry generates no `:id/edit` route. */
export function ContractTypeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: ContractType) => {
    void queryClient.invalidateQueries({ queryKey: contractTypeKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  return <ContractTypeForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: CONTRACT_TYPES_DOMAIN,
  basePath: `/${CONTRACT_TYPES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.contractTypes',
  DetailScreen: ContractTypeDetailScreen,
  FormScreen: ContractTypeFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
