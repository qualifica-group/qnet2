import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import { AGENT_FISCAL_PROFILES_DOMAIN, type AgentFiscalProfileFormMode } from '@/features/agent-fiscal-profiles/types'

/** Metadata-loading state driving what `AgentFiscalProfileForm` renders. */
export type AgentFiscalProfileFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form: edit mode seeds them
 * from the loaded detail, create mode fetches `GET /meta/agent-fiscal-profiles` once.
 */
export function useAgentFiscalProfileFormMeta(mode: AgentFiscalProfileFormMode): AgentFiscalProfileFormMetaState {
  const metaQuery = useResourceMeta(AGENT_FISCAL_PROFILES_DOMAIN, mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.profile.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
