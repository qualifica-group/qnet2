import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteWorkflowFormMode } from '@/features/quote-workflows/types'

/** Metadata-loading state driving what `QuoteWorkflowForm` renders. */
export type QuoteWorkflowFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.quoteWorkflow.permissions`, fetched by the `show` endpoint);
 * create mode fetches the create-context metadata (`GET
 * /meta/quote-workflows`) once. Only `name`/`is_active` are covered by
 * the field-permission catalogue (`QuoteWorkflowsAuthorization`); the
 * nested `criteria`/`statuses` collections are edited via their own request
 * payload keys and are not field-permission-gated.
 */
export function useQuoteWorkflowFormMeta(
  mode: QuoteWorkflowFormMode,
): QuoteWorkflowFormMetaState {
  const metaQuery = useResourceMeta('quote-workflows', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.quoteWorkflow.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
