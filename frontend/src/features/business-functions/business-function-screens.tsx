/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchBusinessFunction } from '@/features/business-functions/api'
import { BusinessFunctionForm } from '@/features/business-functions/business-function-form'
import { BusinessFunctionDetailView } from '@/features/business-functions/business-function-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { BusinessFunctionDetail } from '@/features/business-functions/types'

/** Query key for a single business function's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['business-functions', 'detail', id] as const
}

/**
 * Content-only `business-functions` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages. Moved verbatim from `BusinessFunctionsTable`'s inline loaders.
 */
export function BusinessFunctionDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: businessFunction,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchBusinessFunction(id))

  if (isError) {
    return (
      <DetailError
        message={t('businessFunctions.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !businessFunction) {
    return <DetailLoading />
  }

  return <BusinessFunctionDetailView businessFunction={businessFunction} onEdit={onEdit} />
}

export function BusinessFunctionFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: BusinessFunctionDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <BusinessFunctionForm
        mode={{ type: 'create' }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <BusinessFunctionEditScreen
      businessFunctionId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface BusinessFunctionEditScreenProps {
  businessFunctionId: number
  onSuccess: (businessFunction: BusinessFunctionDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized business function detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather than
 * a stale snapshot.
 */
function BusinessFunctionEditScreen({
  businessFunctionId,
  onSuccess,
  onCancel,
}: BusinessFunctionEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: businessFunction,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(businessFunctionId), () =>
    fetchBusinessFunction(businessFunctionId),
  )

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('businessFunctions.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !businessFunction) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <BusinessFunctionForm
      mode={{ type: 'edit', businessFunction }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'business-functions',
  basePath: '/business-functions',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.businessFunctions',
  DetailScreen: BusinessFunctionDetailScreen,
  FormScreen: BusinessFunctionFormScreen,
  // The record card renders its own Edit action, so the generic page header
  // must not stack a second button — the same registration Opportunita',
  // Utenti and Lead carry.
  detailOwnsEditAction: true,
}
