import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError } from '@/components/detail/detail-panel'
import { useAuth } from '@/features/auth/use-auth'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { fetchPurchaseRequest, purchaseRequestKeys } from '@/features/purchase-requests/api'
import { PurchaseRequestForm } from '@/features/purchase-requests/purchase-request-form'
import { toResourcePermissions } from '@/features/purchase-requests/purchase-request-permissions'
import { PURCHASE_REQUESTS_DOMAIN, type PurchaseRequest } from '@/features/purchase-requests/types'

const LIST_PATH = `/${PURCHASE_REQUESTS_DOMAIN}`

/**
 * Remount key of the form: a line status change does not touch the request's
 * `updated_at`, so the line statuses are part of the key too.
 */
function formKey(request: PurchaseRequest | undefined): string {
  if (!request) {
    return 'new'
  }
  return `${request.updated_at}|${request.status}|${request.lines.map((line) => line.status).join(',')}`
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
 * Loads what the RDA form needs and mounts it: the fresh detail (edit, which
 * also carries the field permissions) or the create-context metadata
 * (`GET /meta/purchase-requests`). Remounts the form on every server change
 * (`updated_at`) so it always restarts from the saved values.
 */
export function PurchaseRequestFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { user } = useAuth()
  const isEdit = id !== undefined
  const detail = useEntityDetail(
    purchaseRequestKeys.detail(id ?? 0),
    () => fetchPurchaseRequest(id as number),
    isEdit,
  )
  const meta = useResourceMeta(PURCHASE_REQUESTS_DOMAIN, !isEdit)
  useBreadcrumbTitle(`${LIST_PATH}/${id ?? 'new'}`, detail.data?.subject)

  const handleSaved = (saved: PurchaseRequest) => {
    if (!isEdit) {
      void navigate(`${LIST_PATH}/${saved.id}`)
    }
  }
  const goToList = () => void navigate(LIST_PATH)

  if (isEdit && detail.isError) {
    return (
      <DetailError
        error={detail.error}
        message={t('purchaseRequests.form.loadError')}
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

  const permissions = isEdit && detail.data ? toResourcePermissions(detail.data) : meta.data?.permissions
  if (!user || !permissions || (isEdit && (detail.isLoading || !detail.data))) {
    return <FormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <PurchaseRequestForm
        key={formKey(detail.data)}
        request={detail.data}
        currentUser={{ id: user.id, name: user.name }}
        onSaved={handleSaved}
        onCancel={goToList}
        onDeleted={goToList}
      />
    </ResourcePermissionsProvider>
  )
}
