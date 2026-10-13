/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
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

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: COST_TYPES_DOMAIN,
  basePath: `/${COST_TYPES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.costTypes',
  DetailScreen: CostTypeDetailScreen,
  FormScreen: CostTypeFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
