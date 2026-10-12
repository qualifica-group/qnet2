/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { costTypeKeys, fetchCostType } from '@/features/cost-types/api'
import { CostTypeForm } from '@/features/cost-types/cost-type-form'
import { CostTypeDetailView } from '@/features/cost-types/cost-type-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import { COST_TYPES_DOMAIN, type CostTypeDetail } from '@/features/cost-types/types'

/**
 * Content-only `cost-types` screens for the module registry (spec 0042):
 * reused by the modal Sheet (`useModuleOpener`) and the generic dedicated pages.
 */
export function CostTypeDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: costType,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(costTypeKeys.detail(id), () => fetchCostType(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('costTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !costType) {
    return <DetailLoading />
  }

  return <CostTypeDetailView costType={costType} onEdit={onEdit} />
}

export function CostTypeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: CostTypeDetail) => {
    void queryClient.invalidateQueries({ queryKey: costTypeKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <CostTypeForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <CostTypeEditScreen costTypeId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface CostTypeEditScreenProps {
  costTypeId: number
  onSuccess: (costType: CostTypeDetail) => void
  onCancel: () => void
}

/** Fetches the fresh detail before mounting the edit form so the PUT starts from authoritative values. */
function CostTypeEditScreen({ costTypeId, onSuccess, onCancel }: CostTypeEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: costType,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(costTypeKeys.detail(costTypeId), () => fetchCostType(costTypeId))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('costTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !costType) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <CostTypeForm mode={{ type: 'edit', costType }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: COST_TYPES_DOMAIN,
  basePath: `/${COST_TYPES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.costTypes',
  DetailScreen: CostTypeDetailScreen,
  FormScreen: CostTypeFormScreen,
  detailOwnsEditAction: true,
}
