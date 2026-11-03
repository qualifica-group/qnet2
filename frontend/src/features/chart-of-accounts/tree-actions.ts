import type { TreeRow } from '@/features/chart-of-accounts/tree-model'
import type { ChartTreeAbilities } from '@/features/chart-of-accounts/types'

export type RowActionKey = 'newAccount' | 'newSubAccount' | 'edit' | 'delete' | 'activity' | 'place'

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
      const child = row.group.kind === 'revenue' ? abilities.revenue_types : abilities.cost_types
      if (child.create) actions.push('newSubAccount')
      if (own.update) actions.push('edit')
      if (own.view_activity) actions.push('activity')
      if (own.delete) actions.push('delete')
      return actions
    }
    case 'sub': {
      const own = row.node.source === 'revenue_type' ? abilities.revenue_types : abilities.cost_types
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
