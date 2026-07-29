/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchQuote, quoteDetailQueryKey } from '@/features/quotes/api'
import { QuoteForm, QuoteFormSkeleton } from '@/features/quotes/quote-form'
import { QuoteDetailView } from '@/features/quotes/quote-detail'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { QuoteDetail } from '@/features/quotes/types'

/**
 * Content-only `quotes` screens for the module registry (spec 0042): fetch +
 * the existing presentational view/form, no page chrome. `quotes` uses
 * `OPEN_MODE_PAGE` (the tabbed form + always-visible economic summary does
 * not fit a modal Sheet well) WITHOUT `generateRoutes: false`: unlike
 * `products`/`referents`/`registries` (which keep their own bespoke pages),
 * quotes has no bespoke page of its own — the generic
 * `ModuleDetailPage`/`ModuleFormPage` (from `buildModuleRoutes()`) host these
 * screens directly for `new`/`:id`/`:id/edit`; only the list route
 * (`/quotes`) stays declared by hand in `router.tsx`, same as every module.
 */
export function QuoteDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: quote,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(quoteDetailQueryKey(id), () => fetchQuote(id))

  if (isError) {
    return (
      <DetailError
        message={t('quotes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !quote) {
    return <DetailLoading />
  }

  return <QuoteDetailView quote={quote} />
}

export function QuoteFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: QuoteDetail) => {
    queryClient.invalidateQueries({ queryKey: quoteDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <QuoteForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return <QuoteEditScreen quoteId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface QuoteEditScreenProps {
  quoteId: number
  onSuccess: (quote: QuoteDetail) => void
  onCancel: () => void
}

/** Fetches the fresh, re-authorized quote detail before mounting the edit form, so the partial PATCH starts from authoritative values. */
function QuoteEditScreen({ quoteId, onSuccess, onCancel }: QuoteEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: quote,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(quoteDetailQueryKey(quoteId), () => fetchQuote(quoteId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('quotes.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !quote) {
    return <QuoteFormSkeleton />
  }

  return <QuoteForm mode={{ type: 'edit', quote }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'quotes',
  basePath: '/quotes',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.quotes',
  DetailScreen: QuoteDetailScreen,
  FormScreen: QuoteFormScreen,
}
