import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { ContractStatusFormMode } from '@/features/contract-statuses/types'

/** Metadata-loading state driving what `ContractStatusForm` renders. */
export type ContractStatusFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail
 * (`mode.contractStatus.permissions`, fetched by the `show` endpoint); create
 * mode fetches the create-context metadata (`GET /meta/contract-statuses`) once.
 */
export function useContractStatusFormMeta(
  mode: ContractStatusFormMode,
): ContractStatusFormMetaState {
  const metaQuery = useResourceMeta('contract-statuses', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.contractStatus.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
