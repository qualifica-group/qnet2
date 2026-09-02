/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchWorkOrder } from '@/features/work-orders/api'
import { WorkOrderForm } from '@/features/work-orders/work-order-form'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { WorkOrderDetail } from '@/features/work-orders/types'

/** Query key for a single work order's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['work-orders', 'detail', id] as const
}

/**
 * Content-only `work-orders` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused as-is
 * by the generic dedicated pages (`ModuleDetailPage`/`ModuleFormPage`) and by
 * the modal Sheet (`useModuleOpener`), whichever the user's preference picks.
 */
export function WorkOrderDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: workOrder,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchWorkOrder(id))

  if (isError) {
    return (
      <DetailError
        message={t('workOrders.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !workOrder) {
    return <DetailLoading />
  }

  return <WorkOrderDetailView workOrder={workOrder} />
}

export function WorkOrderFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: WorkOrderDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <WorkOrderForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <WorkOrderEditScreen workOrderId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface WorkOrderEditScreenProps {
  workOrderId: number
  onSuccess: (workOrder: WorkOrderDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized work order detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function WorkOrderEditScreen({ workOrderId, onSuccess, onCancel }: WorkOrderEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: workOrder,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(workOrderId), () => fetchWorkOrder(workOrderId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('workOrders.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !workOrder) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <WorkOrderForm mode={{ type: 'edit', workOrder }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'work-orders',
  basePath: '/work-orders',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.workOrders',
  DetailScreen: WorkOrderDetailScreen,
  FormScreen: WorkOrderFormScreen,
}
