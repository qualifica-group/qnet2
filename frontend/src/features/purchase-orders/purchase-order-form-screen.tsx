import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError } from '@/components/detail/detail-panel'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { fetchPurchaseOrder, purchaseOrderKeys } from '@/features/purchase-orders/api'
import { PurchaseOrderDetailView } from '@/features/purchase-orders/purchase-order-detail'
import { PurchaseOrderForm } from '@/features/purchase-orders/purchase-order-form'
import { PURCHASE_ORDERS_DOMAIN, type PurchaseOrder } from '@/features/purchase-orders/types'

const LIST_PATH = `/${PURCHASE_ORDERS_DOMAIN}`

/** Remount key of the detail: the order's `updated_at` and status change on every server-side change. */
function detailKey(order: PurchaseOrder): string {
  return `${order.updated_at}|${order.status}`
}

function ScreenSkeleton() {
  return (
    <div className="flex flex-col gap-3" aria-hidden="true">
      <Skeleton className="h-9 w-full" />
      <Skeleton className="h-40 w-full" />
      <Skeleton className="h-40 w-full" />
    </div>
  )
}

/** The saved ODA, edited in place (spec 0257). Remounts on every server change so it restarts from the saved values. */
function PurchaseOrderDetailScreen({ id }: { id: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const detail = useEntityDetail(purchaseOrderKeys.detail(id), () => fetchPurchaseOrder(id))
  useBreadcrumbTitle(`${LIST_PATH}/${id}`, detail.data?.code)
  const goToList = () => void navigate(LIST_PATH)

  if (detail.isError) {
    return (
      <DetailError
        error={detail.error}
        message={t('purchaseOrders.form.loadError')}
        retryLabel={t('common.retry')}
        onRetry={detail.refetch}
      />
    )
  }
  if (detail.isLoading || !detail.data) {
    return <ScreenSkeleton />
  }

  return <PurchaseOrderDetailView key={detailKey(detail.data)} order={detail.data} onBack={goToList} onDeleted={goToList} />
}

/**
 * A new ODA on the create-context metadata (`GET /meta/purchase-orders`).
 * After the save it goes to the first created ODA (the only one, or the
 * first of the monthly copies); leaving unsaved asks first (spec 0257 D-8).
 */
function PurchaseOrderCreateScreen() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const meta = useResourceMeta(PURCHASE_ORDERS_DOMAIN, true)
  useBreadcrumbTitle(`${LIST_PATH}/new`, undefined)
  const leaveGuard = useFormLeaveGuard({
    title: t('purchaseOrderForm.leaveConfirm.title'),
    description: t('purchaseOrderForm.leaveConfirm.description'),
    confirmLabel: t('purchaseOrderForm.leaveConfirm.confirm'),
    cancelLabel: t('purchaseOrderForm.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSaved = (saved: PurchaseOrder) => {
    // Saved: the navigation to the new ODA is no "leaving".
    leaveGuard.allowLeave()
    void navigate(`${LIST_PATH}/${saved.id}`)
  }
  const handleCancel = () => {
    void leaveGuard.confirmLeave().then((leave) => {
      if (leave) {
        void navigate(LIST_PATH)
      }
    })
  }

  if (meta.isError) {
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
  if (!meta.data) {
    return <ScreenSkeleton />
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <ResourcePermissionsProvider permissions={meta.data.permissions}>
        <PurchaseOrderForm onSaved={handleSaved} onCancel={handleCancel} />
      </ResourcePermissionsProvider>
    </>
  )
}

/** The ODA page body: the in-place detail of a saved ODA, or the create form. */
export function PurchaseOrderFormScreen({ id }: { id?: number }) {
  return id === undefined ? <PurchaseOrderCreateScreen /> : <PurchaseOrderDetailScreen id={id} />
}
