/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { contractTypeKeys, fetchContractType } from '@/features/contract-types/api'
import { ContractTypeForm } from '@/features/contract-types/contract-type-form'
import { ContractTypeDetailView } from '@/features/contract-types/contract-type-detail'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleScreens } from '@/features/modules/types'
import { type ContractType } from '@/features/contract-types/types'

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

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: ContractTypeDetailScreen,
  FormScreen: ContractTypeFormScreen,
}
