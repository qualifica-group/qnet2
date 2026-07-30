import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { DocumentLayoutFormMode } from '@/features/document-layouts/types'

/** Metadata-loading state driving what `DocumentLayoutForm` renders. */
export type DocumentLayoutFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.documentLayout.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/document-layouts`)
 * once. `code`/`module` `.editable` differ between the two: true only in
 * create (spec 0069 `field_permissions`).
 */
export function useDocumentLayoutFormMeta(mode: DocumentLayoutFormMode): DocumentLayoutFormMetaState {
  const metaQuery = useResourceMeta('document-layouts', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.documentLayout.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
