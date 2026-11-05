import type { TreeRow } from '@/features/chart-of-accounts/tree-model'
import type {
  ChartAccountKind,
  ChartTreeAbilities,
  ResourceAbilities,
  SubAccountSource,
} from '@/features/chart-of-accounts/types'

export type RowActionKey = 'newAccount' | 'newSubAccount' | 'edit' | 'delete' | 'activity' | 'place'

function childAbilities(kind: ChartAccountKind, abilities: ChartTreeAbilities): ResourceAbilities {
  if (kind === 'revenue') return abilities.revenue_types
  return kind === 'financial' ? abilities.treasury_types : abilities.cost_types
}

function sourceAbilities(source: SubAccountSource, abilities: ChartTreeAbilities): ResourceAbilities {
  if (source === 'revenue_type') return abilities.revenue_types
  return source === 'treasury_type' ? abilities.treasury_types : abilities.cost_types
}

/**
 * Menu actions a row offers, from the tree endpoint's `abilities` (UX gate only,
 * the backend re-authorizes). A new sub-account follows the group kind: revenue
 * types for revenue groups, cost types for cost groups.
 */
export function rowActions(row: TreeRow, abilities: ChartTreeAbilities): RowActionKey[] {
  const actions: RowActionKey[] = []

  switch (row.type) {
    case 'unplaced':
      return actions
    case 'group': {
      const own = abilities.chart_accounts
      if (own.create) actions.push('newAccount')
      if (own.update) actions.push('edit')
      if (own.view_activity) actions.push('activity')
      if (own.delete) actions.push('delete')
      return actions
    }
    case 'account': {
      const own = abilities.chart_accounts
      const child = childAbilities(row.group.kind, abilities)
      if (child.create) actions.push('newSubAccount')
      if (own.update) actions.push('edit')
      if (own.view_activity) actions.push('activity')
      if (own.delete) actions.push('delete')
      return actions
    }
    case 'sub': {
      const own = sourceAbilities(row.node.source, abilities)
      if (row.account === null) {
        if (own.update) actions.push('place')
      } else if (own.update) {
        actions.push('edit')
      }
      if (own.view_activity) actions.push('activity')
      if (own.delete) actions.push('delete')
      return actions
    }
  }
}
