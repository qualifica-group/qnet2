import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import { COST_TYPES_DOMAIN, type CostTypeFormMode } from '@/features/cost-types/types'

/** Metadata-loading state driving what `CostTypeForm` renders. */
export type CostTypeFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form: edit mode seeds them
 * from the loaded detail, create mode fetches `GET /meta/cost-types` once.
 */
export function useCostTypeFormMeta(mode: CostTypeFormMode): CostTypeFormMetaState {
  const metaQuery = useResourceMeta(COST_TYPES_DOMAIN, mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.costType.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
