import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * `renderItem` for an `AsyncPaginatedSelect` over `cost-types/for-select`:
 * projects the item's `meta.color` onto `CostTypeBadge`, so every cost type
 * picker (order lines, Generate orders) renders the colored option and trigger.
 */
export function costTypeOptionRenderer(item: ForSelectItem) {
  const meta = (item as ForSelectItem & { meta?: { color?: string | null } }).meta
  return <CostTypeBadge name={item.label} color={meta?.color} />
}
