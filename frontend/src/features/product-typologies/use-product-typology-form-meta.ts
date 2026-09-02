import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { ProductTypologyFormMode } from '@/features/product-typologies/types'

/** Metadata-loading state driving what `ProductTypologyForm` renders. */
export type ProductTypologyFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.productTypology.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/product-typologies`)
 * once. `code.editable` differs between the two (D-1): true only in create.
 */
export function useProductTypologyFormMeta(mode: ProductTypologyFormMode): ProductTypologyFormMetaState {
  const metaQuery = useResourceMeta('product-typologies', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.productTypology.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
