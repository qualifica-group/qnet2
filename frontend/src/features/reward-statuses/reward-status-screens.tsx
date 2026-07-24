/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRewardStatus } from '@/features/reward-statuses/api'
import { RewardStatusForm } from '@/features/reward-statuses/reward-status-form'
import { RewardStatusDetailView } from '@/features/reward-statuses/reward-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { RewardStatusDetail } from '@/features/reward-statuses/types'

/** Query key for a single reward status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['reward-statuses', 'detail', id] as const
}

/**
 * Content-only `reward-statuses` screens for the module registry (spec
 * 0060): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function RewardStatusDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: rewardStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchRewardStatus(id))

  if (isError) {
    return (
      <DetailError
        message={t('rewardStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !rewardStatus) {
    return <DetailLoading />
  }

  return <RewardStatusDetailView rewardStatus={rewardStatus} />
}

export function RewardStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: RewardStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <RewardStatusForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  return (
    <RewardStatusEditScreen
      rewardStatusId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface RewardStatusEditScreenProps {
  rewardStatusId: number
  onSuccess: (rewardStatus: RewardStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized reward status detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function RewardStatusEditScreen({
  rewardStatusId,
  onSuccess,
  onCancel,
}: RewardStatusEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: rewardStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(rewardStatusId), () => fetchRewardStatus(rewardStatusId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('rewardStatuses.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !rewardStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <RewardStatusForm
      mode={{ type: 'edit', rewardStatus }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'reward-statuses',
  basePath: '/reward-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.rewardStatuses',
  DetailScreen: RewardStatusDetailScreen,
  FormScreen: RewardStatusFormScreen,
}
