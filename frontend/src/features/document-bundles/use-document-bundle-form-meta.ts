import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { DocumentBundleFormMode } from '@/features/document-bundles/types'

/** Metadata-loading state driving what `DocumentBundleForm` renders. */
export type DocumentBundleFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.documentBundle.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/document-bundles`)
 * once.
 */
export function useDocumentBundleFormMeta(mode: DocumentBundleFormMode): DocumentBundleFormMetaState {
  const metaQuery = useResourceMeta('document-bundles', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.documentBundle.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
