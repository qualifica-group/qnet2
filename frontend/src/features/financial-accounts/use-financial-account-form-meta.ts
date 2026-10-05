import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { FinancialAccountFormMode } from '@/features/financial-accounts/types'

/** Metadata-loading state driving what `FinancialAccountForm` renders. */
export type FinancialAccountFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail; create mode fetches the
 * create-context metadata (`GET /meta/financial-accounts`) once.
 */
export function useFinancialAccountFormMeta(
  mode: FinancialAccountFormMode,
): FinancialAccountFormMetaState {
  const metaQuery = useResourceMeta('financial-accounts', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.financialAccount.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
