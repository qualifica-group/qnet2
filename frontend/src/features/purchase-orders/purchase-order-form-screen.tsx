import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError } from '@/components/detail/detail-panel'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { fetchPurchaseOrder, purchaseOrderKeys } from '@/features/purchase-orders/api'
import { PurchaseOrderForm } from '@/features/purchase-orders/purchase-order-form'
import { toOrderResourcePermissions } from '@/features/purchase-orders/purchase-order-permissions'
import { PURCHASE_ORDERS_DOMAIN, type PurchaseOrder } from '@/features/purchase-orders/types'

const LIST_PATH = `/${PURCHASE_ORDERS_DOMAIN}`

/** Remount key of the form: the order's `updated_at` and status change on every server-side change. */
function formKey(order: PurchaseOrder | undefined): string {
  return order ? `${order.updated_at}|${order.status}` : 'new'
}

function FormSkeleton() {
  return (
    <div className="flex flex-col gap-3" aria-hidden="true">
      <Skeleton className="h-9 w-full" />
      <Skeleton className="h-40 w-full" />
      <Skeleton className="h-40 w-full" />
    </div>
  )
}

/**
 * Loads what the ODA form needs and mounts it: the fresh detail (edit, which
 * also carries the field permissions) or the create-context metadata
 * (`GET /meta/purchase-orders`). Remounts the form on every server change so it
 * always restarts from the saved values. After a create, goes to the first
 * created ODA (the only one, or the first of the monthly copies).
 */
export function PurchaseOrderFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const isEdit = id !== undefined
  const detail = useEntityDetail(purchaseOrderKeys.detail(id ?? 0), () => fetchPurchaseOrder(id as number), isEdit)
  const meta = useResourceMeta(PURCHASE_ORDERS_DOMAIN, !isEdit)
  useBreadcrumbTitle(`${LIST_PATH}/${id ?? 'new'}`, detail.data?.code)

  const handleSaved = (saved: PurchaseOrder) => {
    if (!isEdit) {
      void navigate(`${LIST_PATH}/${saved.id}`)
    }
  }
  const goToList = () => void navigate(LIST_PATH)

  if (isEdit && detail.isError) {
    return (
      <DetailError
        error={detail.error}
        message={t('purchaseOrders.form.loadError')}
        retryLabel={t('common.retry')}
        onRetry={detail.refetch}
      />
    )
  }
  if (!isEdit && meta.isError) {
    return (
      <div className="flex flex-col items-start gap-3">
        <p role="alert" className="text-sm text-destructive">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void meta.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  const permissions = isEdit && detail.data ? toOrderResourcePermissions(detail.data) : meta.data?.permissions
  if (!permissions || (isEdit && (detail.isLoading || !detail.data))) {
    return <FormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <PurchaseOrderForm
        key={formKey(detail.data)}
        order={detail.data}
        onSaved={handleSaved}
        onCancel={goToList}
        onDeleted={goToList}
      />
    </ResourcePermissionsProvider>
  )
}
