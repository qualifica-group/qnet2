import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'

/** Metadata-loading state driving what `ProductCategoryForm` renders. */
export type ProductCategoryFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.category.permissions`, fetched by the `show` endpoint); create and
 * duplicate (both submit a create) fetch the create-context metadata
 * (`GET /meta/product-categories`) once.
 */
export function useProductCategoryFormMeta(
  mode: ProductCategoryFormMode,
): ProductCategoryFormMetaState {
  const metaQuery = useResourceMeta('product-categories', mode.type !== 'edit')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.category.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
