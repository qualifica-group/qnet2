/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchQuoteWorkflow } from '@/features/quote-workflows/api'
import { QuoteWorkflowForm } from '@/features/quote-workflows/quote-workflow-form'
import { QuoteWorkflowDetailView } from '@/features/quote-workflows/quote-workflow-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { QuoteWorkflowDetail } from '@/features/quote-workflows/types'

/** Query key for a single opportunity workflow's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['quote-workflows', 'detail', id] as const
}

/**
 * Content-only `quote-workflows` screens for the module registry (spec
 * 0042): fetch + the existing presentational view/form, no page chrome.
 * Reused as-is by the modal Sheet (`useModuleOpener`) and by the generic
 * dedicated pages (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function QuoteWorkflowDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: quoteWorkflow,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchQuoteWorkflow(id))

  if (isError) {
    return (
      <DetailError
        message={t('quoteWorkflows.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !quoteWorkflow) {
    return <DetailLoading />
  }

  return <QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow} />
}

export function QuoteWorkflowFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: QuoteWorkflowDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <QuoteWorkflowForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
    )
  }

  return (
    <QuoteWorkflowEditScreen
      quoteWorkflowId={mode.id}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

interface QuoteWorkflowEditScreenProps {
  quoteWorkflowId: number
  onSuccess: (quoteWorkflow: QuoteWorkflowDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized opportunity workflow detail before
 * mounting the edit form, so the PATCH starts from authoritative values
 * rather than a stale snapshot.
 */
function QuoteWorkflowEditScreen({
  quoteWorkflowId,
  onSuccess,
  onCancel,
}: QuoteWorkflowEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: quoteWorkflow,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(quoteWorkflowId), () => fetchQuoteWorkflow(quoteWorkflowId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('quoteWorkflows.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !quoteWorkflow) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <QuoteWorkflowForm
      mode={{ type: 'edit', quoteWorkflow }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'quote-workflows',
  basePath: '/quote-workflows',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.quoteWorkflows',
  DetailScreen: QuoteWorkflowDetailScreen,
  FormScreen: QuoteWorkflowFormScreen,
}
