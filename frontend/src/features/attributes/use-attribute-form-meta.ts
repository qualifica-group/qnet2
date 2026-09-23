import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { AttributeFormMode } from '@/features/attributes/types'

/** Metadata-loading state driving what `AttributeForm` renders. */
export type AttributeFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.attribute.permissions`, fetched by the `show` endpoint); create and
 * duplicate (both submit a create) fetch the create-context metadata
 * (`GET /meta/attributes`) once.
 */
export function useAttributeFormMeta(mode: AttributeFormMode): AttributeFormMetaState {
  const metaQuery = useResourceMeta('attributes', mode.type !== 'edit')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.attribute.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
