/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchRewardStatus, rewardStatusDetailQueryKey } from '@/features/reward-statuses/api'
import { RewardStatusForm } from '@/features/reward-statuses/reward-status-form'
import { RewardStatusDetailView } from '@/features/reward-statuses/reward-status-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { RewardStatusDetail } from '@/features/reward-statuses/types'

/**
 * Content-only `reward-statuses` screens for the module registry (spec 0042):
 * fetch + the presentational view/form, no page chrome. The detail edits its
 * rows in place, so `onEdit` is never used; each save reports through
 * `onChanged`.
 */
export function RewardStatusDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: rewardStatus,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(rewardStatusDetailQueryKey(id), () => fetchRewardStatus(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('rewardStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !rewardStatus) {
    return <DetailLoading />
  }

  return <RewardStatusDetailView rewardStatus={rewardStatus} onChanged={onChanged} />
}

/**
 * Create only: there is no edit form, and the registry generates no
 * `:id/edit` route. Leaving a record being created — Cancel, the Sheet's
 * X/overlay/Esc, a link, a reload — always asks first.
 */
export function RewardStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const leaveGuard = useFormLeaveGuard({
    title: t('rewardStatuses.form.leaveConfirm.title'),
    description: t('rewardStatuses.form.leaveConfirm.description'),
    confirmLabel: t('rewardStatuses.form.leaveConfirm.confirm'),
    cancelLabel: t('rewardStatuses.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: RewardStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: rewardStatusDetailQueryKey(saved.id) })
    // Saved: the navigation to the new record's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = () => {
    void leaveGuard.confirmLeave().then((leave) => {
      if (leave) {
        onCancel()
      }
    })
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <RewardStatusForm onSuccess={handleSuccess} onCancel={handleCancel} />
    </>
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
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own identity band (title + actions on one row), so
  // the hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
