import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { fetchQuoteNextCode } from '@/features/quotes/api'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import type { QuoteCreateFormMode, QuoteDetail } from '@/features/quotes/types'

interface QuoteFormProps {
  mode: QuoteCreateFormMode
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (quote: QuoteDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * RHF + Zod form used for creating a quote — editing happens in place on the
 * detail (spec 0197). Metadata-driven (spec 0004): resolves the create-context
 * `ResourcePermissions` (`GET /meta/quotes`) before rendering, and suggests
 * the next sequential `code` (`GET /quotes/next-code`, D-13/AC-082), kept
 * uncached (`staleTime`/`gcTime` 0) so every new form gets a fresh suggestion.
 */
export function QuoteForm({ mode, onSuccess, onCancel }: QuoteFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta('quotes', true)

  const nextCode = useQuery({
    queryKey: ['quotes', 'next-code'],
    queryFn: fetchQuoteNextCode,
    staleTime: 0,
    gcTime: 0,
  })

  if (metaQuery.isPending || nextCode.isLoading) {
    return <RecordFormSkeleton />
  }

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data?.permissions ?? null}>
      <QuoteFormBody mode={mode} onSuccess={onSuccess} onCancel={onCancel} initialCode={nextCode.data ?? ''} />
    </ResourcePermissionsProvider>
  )
}
