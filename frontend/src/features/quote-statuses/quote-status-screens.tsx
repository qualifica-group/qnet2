/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchQuoteStatus } from '@/features/quote-statuses/api'
import { QuoteStatusForm } from '@/features/quote-statuses/quote-status-form'
import { QuoteStatusDetailView } from '@/features/quote-statuses/quote-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { QuoteStatusDetail } from '@/features/quote-statuses/types'

/** Query key for a single quote status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['quote-statuses', 'detail', id] as const
}

/**
 * Content-only `quote-statuses` screens for the module registry (spec 0042):
 * fetch + the existing presentational view/form, no page chrome. Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function QuoteStatusDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: quoteStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchQuoteStatus(id))

  if (isError) {
    return (
      <DetailError
        message={t('quoteStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !quoteStatus) {
    return <DetailLoading />
  }

  return <QuoteStatusDetailView quoteStatus={quoteStatus} />
}

export function QuoteStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: QuoteStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <QuoteStatusForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  return (
    <QuoteStatusEditScreen
      quoteStatusId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface QuoteStatusEditScreenProps {
  quoteStatusId: number
  onSuccess: (quoteStatus: QuoteStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized quote status detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than a stale snapshot.
 */
function QuoteStatusEditScreen({
  quoteStatusId,
  onSuccess,
  onCancel,
}: QuoteStatusEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: quoteStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(quoteStatusId), () => fetchQuoteStatus(quoteStatusId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('quoteStatuses.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !quoteStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <QuoteStatusForm
      mode={{ type: 'edit', quoteStatus }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'quote-statuses',
  basePath: '/quote-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.quoteStatuses',
  DetailScreen: QuoteStatusDetailScreen,
  FormScreen: QuoteStatusFormScreen,
}
