import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { RewardTypeFormMode } from '@/features/reward-types/types'

/** Metadata-loading state driving what `RewardTypeForm` renders. */
export type RewardTypeFormMetaState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'ready'; permissions: ResourcePermissions }

/**
 * Resolves the `ResourcePermissions` backing the form (spec 0004). Edit mode
 * seeds it from the already-loaded instance detail (`mode.rewardType.permissions`,
 * fetched by the `show` endpoint); create mode fetches the create-context
 * metadata (`GET /meta/reward-types`) once.
 */
export function useRewardTypeFormMeta(mode: RewardTypeFormMode): RewardTypeFormMetaState {
  const metaQuery = useResourceMeta('reward-types', mode.type === 'create')

  if (mode.type === 'edit') {
    return { status: 'ready', permissions: mode.rewardType.permissions }
  }
  if (metaQuery.isError) {
    return { status: 'error', retry: () => void metaQuery.refetch() }
  }
  if (!metaQuery.data) {
    return { status: 'loading' }
  }
  return { status: 'ready', permissions: metaQuery.data.permissions }
}
