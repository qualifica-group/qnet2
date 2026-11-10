/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { costTypeKeys, fetchCostType } from '@/features/cost-types/api'
import { CostTypeForm } from '@/features/cost-types/cost-type-form'
import { CostTypeDetailView } from '@/features/cost-types/cost-type-detail'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import { type CostTypeDetail } from '@/features/cost-types/types'

/**
 * Content-only `cost-types` screens for the module registry (spec 0042). The
 * detail edits its rows in place, so `onEdit` is never used; each save
 * reports through `onChanged`.
 */
export function CostTypeDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
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

  return <CostTypeDetailView costType={costType} onChanged={onChanged} />
}

/** Create only: there is no edit form, and the registry generates no `:id/edit` route. */
export function CostTypeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: CostTypeDetail) => {
    void queryClient.invalidateQueries({ queryKey: costTypeKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  return <CostTypeForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: CostTypeDetailScreen,
  FormScreen: CostTypeFormScreen,
}
