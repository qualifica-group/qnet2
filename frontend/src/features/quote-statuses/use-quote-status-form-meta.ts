import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteStatusFormMode } from '@/features/quote-statuses/types'

/** Metadata-loading state driving what `QuoteStatusForm` renders. */
export type QuoteStatusFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.quoteStatus.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/quote-statuses`) once.
 */
export function useQuoteStatusFormMeta(
  mode: QuoteStatusFormMode,
): QuoteStatusFormMetaState {
  const metaQuery = useResourceMeta('quote-statuses', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.quoteStatus.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
