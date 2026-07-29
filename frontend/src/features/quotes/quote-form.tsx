import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { fetchQuoteNextCode } from '@/features/quotes/api'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import type { QuoteDetail, QuoteFormMode } from '@/features/quotes/types'

interface QuoteFormProps {
  mode: QuoteFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (quote: QuoteDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/** Loading placeholder mirroring the form's real layout, so the swap to the loaded form does not shift the page. */
export function QuoteFormSkeleton() {
  return (
    <div className="flex flex-col gap-4 p-4" aria-hidden="true">
      <Skeleton className="h-9 w-full" />
      <div className="grid gap-3 sm:grid-cols-2">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
      <Skeleton className="h-9 w-64" />
      <Skeleton className="h-40 w-full" />
    </div>
  )
}

/**
 * Reusable RHF + Zod form used for both creating and editing a quote.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/quotes` — and, in create mode only, suggests the next
 * sequential `code` (`GET /quotes/next-code`, D-13/AC-082), kept uncached
 * (`staleTime`/`gcTime` 0, mirrors `ProjectForm`) so every new form gets a
 * fresh suggestion.
 */
export function QuoteForm(props: QuoteFormProps) {
  const { t } = useTranslation()
  const isCreate = props.mode.type === 'create'
  const metaQuery = useResourceMeta('quotes', isCreate)

  const nextCode = useQuery({
    queryKey: ['quotes', 'next-code'],
    queryFn: fetchQuoteNextCode,
    enabled: isCreate,
    staleTime: 0,
    gcTime: 0,
  })

  if (isCreate && (metaQuery.isPending || nextCode.isLoading)) {
    return <QuoteFormSkeleton />
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

  const permissions = props.mode.type === 'edit' ? props.mode.quote.permissions : (metaQuery.data?.permissions ?? null)

  return (
    <ResourcePermissionsProvider permissions={permissions}>
      <QuoteFormBody {...props} initialCode={isCreate ? (nextCode.data ?? '') : undefined} />
    </ResourcePermissionsProvider>
  )
}
