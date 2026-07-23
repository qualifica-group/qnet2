/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRewardType } from '@/features/reward-types/api'
import { RewardTypeForm } from '@/features/reward-types/reward-type-form'
import { RewardTypeDetailView } from '@/features/reward-types/reward-type-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { RewardTypeDetail } from '@/features/reward-types/types'

/** Query key for a single reward type's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['reward-types', 'detail', id] as const
}

/**
 * Content-only `reward-types` screens for the module registry (spec 0058,
 * MT-9), cloned from `opportunity-statuses`: fetch + the existing
 * presentational view/form, no page chrome. Reused as-is by the modal Sheet
 * (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function RewardTypeDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: rewardType,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchRewardType(id))

  if (isError) {
    return (
      <DetailError
        message={t('rewardTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !rewardType) {
    return <DetailLoading />
  }

  return <RewardTypeDetailView rewardType={rewardType} />
}

export function RewardTypeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: RewardTypeDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <RewardTypeForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return (
    <RewardTypeEditScreen rewardTypeId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface RewardTypeEditScreenProps {
  rewardTypeId: number
  onSuccess: (rewardType: RewardTypeDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized reward type detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function RewardTypeEditScreen({ rewardTypeId, onSuccess, onCancel }: RewardTypeEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: rewardType,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(rewardTypeId), () => fetchRewardType(rewardTypeId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('rewardTypes.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !rewardType) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <RewardTypeForm mode={{ type: 'edit', rewardType }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'reward-types',
  basePath: '/reward-types',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.rewardTypes',
  DetailScreen: RewardTypeDetailScreen,
  FormScreen: RewardTypeFormScreen,
}
