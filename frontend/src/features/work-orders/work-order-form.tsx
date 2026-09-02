import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { fetchWorkOrderNextCode } from '@/features/work-orders/api'
import { WorkOrderFormBody } from '@/features/work-orders/work-order-form-body'
import type { WorkOrderDetail, WorkOrderFormMode } from '@/features/work-orders/types'

interface WorkOrderFormProps {
  mode: WorkOrderFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (workOrder: WorkOrderDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/** Loading placeholder mirroring the form's real layout, so the swap to the loaded form does not shift the page. */
function WorkOrderFormSkeleton() {
  return (
    <div className="flex flex-col gap-4 p-4" aria-hidden="true">
      <div className="grid gap-3 sm:grid-cols-2">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
      <Skeleton className="h-9 w-full" />
      <Skeleton className="h-24 w-full" />
    </div>
  )
}

/**
 * Reusable RHF + Zod form used for both creating and editing a work order.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/work-orders` — and, in create mode only, suggests the next
 * sequential `code` (`GET /work-orders/next-code`, D-1), kept uncached
 * (`staleTime`/`gcTime` 0, mirrors `QuoteForm`) so every new form gets a
 * fresh suggestion.
 */
export function WorkOrderForm(props: WorkOrderFormProps) {
  const { t } = useTranslation()
  const isCreate = props.mode.type === 'create'
  const metaQuery = useResourceMeta('work-orders', isCreate)

  const nextCode = useQuery({
    queryKey: ['work-orders', 'next-code'],
    queryFn: fetchWorkOrderNextCode,
    enabled: isCreate,
    staleTime: 0,
    gcTime: 0,
  })

  if (isCreate && (metaQuery.isPending || nextCode.isLoading)) {
    return <WorkOrderFormSkeleton />
  }

  if (isCreate && metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  const permissions =
    props.mode.type === 'edit' ? props.mode.workOrder.permissions : (metaQuery.data?.permissions ?? null)

  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <WorkOrderFormBody {...props} initialCode={isCreate ? (nextCode.data ?? '') : undefined} />
    </ResourcePermissionsProvider>
  )
}
