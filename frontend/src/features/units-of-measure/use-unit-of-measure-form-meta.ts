import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { UnitOfMeasureFormMode } from '@/features/units-of-measure/types'

/** Metadata-loading state driving what `UnitOfMeasureForm` renders. */
export type UnitOfMeasureFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.unitOfMeasure.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/units-of-measure`)
 * once. `code.editable` differs between the two (D-1): true only in create.
 */
export function useUnitOfMeasureFormMeta(mode: UnitOfMeasureFormMode): UnitOfMeasureFormMetaState {
  const metaQuery = useResourceMeta('units-of-measure', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.unitOfMeasure.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
