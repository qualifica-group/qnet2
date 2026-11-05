import type { ReactNode } from 'react'
import type { ForSelectItem } from '@/features/for-select/types'
import { CostSubAccountGroups } from '@/features/chart-of-accounts/sub-account-select/cost-sub-account-groups'
import { subAccountLabel, type SubAccountNode } from '@/features/chart-of-accounts/sub-account-select/sub-account-groups'

/** Item of `GET /api/cost-types/for-select` (spec 0350 D-12): `account === null` = not placed in the chart. */
export interface CostSubAccountItem extends ForSelectItem {
  code: string | null
  path: string | null
  group: SubAccountNode | null
  account: SubAccountNode | null
}

/** Trigger and option text: code + name, name only for a cost type without a code. */
export function costSubAccountRenderItem(item: ForSelectItem): ReactNode {
  const { code } = item as Partial<CostSubAccountItem>
  return <span className="truncate">{subAccountLabel({ code, name: item.label })}</span>
}

function costSubAccountGroupOptions(items: ForSelectItem[], renderRow: (item: ForSelectItem) => ReactNode): ReactNode {
  return <CostSubAccountGroups items={items} renderRow={renderRow} />
}

/**
 * Spread onto an `AsyncPaginatedSelect` (or a wrapper forwarding both props) over
 * `cost-types/for-select`: chart-of-accounts grouping with a trailing "to be placed" group.
 */
export const COST_SUB_ACCOUNT_SELECT_PROPS = {
  renderItem: costSubAccountRenderItem,
  groupOptions: costSubAccountGroupOptions,
}
