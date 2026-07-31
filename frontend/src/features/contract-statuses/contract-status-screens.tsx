/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchContractStatus } from '@/features/contract-statuses/api'
import { ContractStatusForm } from '@/features/contract-statuses/contract-status-form'
import { ContractStatusDetailView } from '@/features/contract-statuses/contract-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { ContractStatusDetail } from '@/features/contract-statuses/types'

/** Query key for a single contract status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['contract-statuses', 'detail', id] as const
}

/**
 * Content-only `contract-statuses` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function ContractStatusDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: contractStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchContractStatus(id))

  if (isError) {
    return (
      <DetailError
        message={t('contractStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !contractStatus) {
    return <DetailLoading />
  }

  return <ContractStatusDetailView contractStatus={contractStatus} />
}

export function ContractStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: ContractStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <ContractStatusForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <ContractStatusEditScreen
      contractStatusId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface ContractStatusEditScreenProps {
  contractStatusId: number
  onSuccess: (contractStatus: ContractStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized contract status detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function ContractStatusEditScreen({
  contractStatusId,
  onSuccess,
  onCancel,
}: ContractStatusEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: contractStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(contractStatusId), () => fetchContractStatus(contractStatusId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('contractStatuses.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !contractStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <ContractStatusForm
      mode={{ type: 'edit', contractStatus }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'contract-statuses',
  basePath: '/contract-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.contractStatuses',
  DetailScreen: ContractStatusDetailScreen,
  FormScreen: ContractStatusFormScreen,
}
