/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchWorkOrderPaymentStatus } from '@/features/work-order-payment-statuses/api'
import { WorkOrderPaymentStatusForm } from '@/features/work-order-payment-statuses/work-order-payment-status-form'
import { WorkOrderPaymentStatusDetailView } from '@/features/work-order-payment-statuses/work-order-payment-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { WorkOrderPaymentStatusDetail } from '@/features/work-order-payment-statuses/types'

/** Query key for a single work order payment status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['work-order-payment-statuses', 'detail', id] as const
}

/**
 * Content-only `work-order-payment-statuses` screens for the module registry (spec
 * 0060): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function WorkOrderPaymentStatusDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: workOrderPaymentStatus,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchWorkOrderPaymentStatus(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('workOrderPaymentStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !workOrderPaymentStatus) {
    return <DetailLoading />
  }

  return <WorkOrderPaymentStatusDetailView workOrderPaymentStatus={workOrderPaymentStatus} onEdit={onEdit} />
}

export function WorkOrderPaymentStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: WorkOrderPaymentStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <WorkOrderPaymentStatusForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  return (
    <WorkOrderPaymentStatusEditScreen
      workOrderPaymentStatusId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface WorkOrderPaymentStatusEditScreenProps {
  workOrderPaymentStatusId: number
  onSuccess: (workOrderPaymentStatus: WorkOrderPaymentStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized work order payment status detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function WorkOrderPaymentStatusEditScreen({
  workOrderPaymentStatusId,
  onSuccess,
  onCancel,
}: WorkOrderPaymentStatusEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: workOrderPaymentStatus,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(detailQueryKey(workOrderPaymentStatusId), () => fetchWorkOrderPaymentStatus(workOrderPaymentStatusId))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('workOrderPaymentStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !workOrderPaymentStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <WorkOrderPaymentStatusForm
      mode={{ type: 'edit', workOrderPaymentStatus }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'work-order-payment-statuses',
  basePath: '/work-order-payment-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.workOrderPaymentStatuses',
  DetailScreen: WorkOrderPaymentStatusDetailScreen,
  FormScreen: WorkOrderPaymentStatusFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}
