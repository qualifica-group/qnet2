import { CountCell } from '@/features/table/cell-renderers'
import { DateCell } from '@/features/table/rich-cells'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * Domain renderer map for `rewarded-referents` (spec 0059 §2). `name`,
 * `registries`, `email` and `phone` are plain scalar strings on the frozen
 * row contract — the generic table's default text rendering already covers
 * them, so only the count/date columns need an override here.
 */
export const rewardedReferentColumnRenderers: TableRendererMap = {
  rewards_count: CountCell,
  pending_rewards_count: CountCell,
  approved_rewards_count: CountCell,
  last_assigned_at: DateCell,
}
