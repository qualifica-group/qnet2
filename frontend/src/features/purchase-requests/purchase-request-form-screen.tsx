import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError } from '@/components/detail/detail-panel'
import { useAuth } from '@/features/auth/use-auth'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { fetchPurchaseRequest, purchaseRequestKeys } from '@/features/purchase-requests/api'
import { PurchaseRequestDetailView } from '@/features/purchase-requests/purchase-request-detail'
import { PurchaseRequestForm } from '@/features/purchase-requests/purchase-request-form'
import { PURCHASE_REQUESTS_DOMAIN, type PurchaseRequest } from '@/features/purchase-requests/types'

const LIST_PATH = `/${PURCHASE_REQUESTS_DOMAIN}`

/**
 * Remount key of the detail: a line status change does not touch the
 * request's `updated_at`, so the line statuses are part of the key too.
 */
function detailKey(request: PurchaseRequest): string {
  return `${request.updated_at}|${request.status}|${request.lines.map((line) => line.status).join(',')}`
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

/** The saved RDA, edited in place (spec 0256). Remounts on every server change so it restarts from the saved values. */
function PurchaseRequestDetailScreen({ id }: { id: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const detail = useEntityDetail(purchaseRequestKeys.detail(id), () => fetchPurchaseRequest(id))
  useBreadcrumbTitle(`${LIST_PATH}/${id}`, detail.data?.subject)
  const goToList = () => void navigate(LIST_PATH)

  if (detail.isError) {
    return (
      <DetailError
        error={detail.error}
        message={t('purchaseRequests.form.loadError')}
        retryLabel={t('common.retry')}
        onRetry={detail.refetch}
      />
    )
  }
  if (detail.isLoading || !detail.data) {
    return <ScreenSkeleton />
  }

  return (
    <PurchaseRequestDetailView
      key={detailKey(detail.data)}
      request={detail.data}
      onBack={goToList}
      onDeleted={goToList}
      onChanged={() => void detail.refetch()}
    />
  )
}

/** A new RDA on the create-context metadata (`GET /meta/purchase-requests`); leaving it unsaved asks first (spec 0256 D-8). */
function PurchaseRequestCreateScreen() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { user } = useAuth()
  const meta = useResourceMeta(PURCHASE_REQUESTS_DOMAIN, true)
  useBreadcrumbTitle(`${LIST_PATH}/new`, undefined)
  const leaveGuard = useFormLeaveGuard({
    title: t('purchaseRequests.form.leaveConfirm.title'),
    description: t('purchaseRequests.form.leaveConfirm.description'),
    confirmLabel: t('purchaseRequests.form.leaveConfirm.confirm'),
    cancelLabel: t('purchaseRequests.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSaved = (saved: PurchaseRequest) => {
    // Saved: the navigation to the new RDA is no "leaving".
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
  if (!user || !meta.data) {
    return <ScreenSkeleton />
  }

  return (
    <>
      {leaveGuard.navigationGuard}
      <ResourcePermissionsProvider permissions={meta.data.permissions}>
        <PurchaseRequestForm currentUser={{ id: user.id, name: user.name }} onSaved={handleSaved} onCancel={handleCancel} />
      </ResourcePermissionsProvider>
    </>
  )
}

/** The RDA page body: the in-place detail of a saved RDA, or the create form. */
export function PurchaseRequestFormScreen({ id }: { id?: number }) {
  return id === undefined ? <PurchaseRequestCreateScreen /> : <PurchaseRequestDetailScreen id={id} />
}
