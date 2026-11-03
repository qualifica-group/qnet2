import type { ChartAccountNode, ChartGroup, ChartTree, SubAccount } from '@/features/chart-of-accounts/types'

/** Key of the virtual group collecting the cost types with no account yet. */
export const UNPLACED_KEY = 'unplaced'

export type TreeRow =
  | { type: 'group'; key: string; depth: 0; node: ChartGroup; expandable: boolean; expanded: boolean }
  | { type: 'account'; key: string; depth: 1; node: ChartAccountNode; group: ChartGroup; expandable: boolean; expanded: boolean }
  | {
      type: 'sub'
      key: string
      depth: 1 | 2
      node: SubAccount
      /** Null for an unplaced cost type. */
      account: ChartAccountNode | null
      group: ChartGroup | null
    }
  | { type: 'unplaced'; key: string; depth: 0; count: number; expandable: true; expanded: boolean }

export const groupKey = (group: ChartGroup) => `group:${group.id}`
export const accountKey = (account: ChartAccountNode) => `account:${account.id}`
export const subKey = (sub: SubAccount) => `${sub.source}:${sub.id}`

/** Keys of every node that has children, i.e. everything "expand all" opens. */
export function expandableKeys(tree: ChartTree): string[] {
  const keys: string[] = []
  for (const group of tree.groups) {
    if (group.accounts.length > 0) keys.push(groupKey(group))
    for (const account of group.accounts) {
      if (account.sub_accounts.length > 0) keys.push(accountKey(account))
    }
  }
  if (tree.unplaced_cost_types.length > 0) keys.push(UNPLACED_KEY)
  return keys
}

/** Initial state: mastri open (their conti visible), conti closed. */
export function defaultExpandedKeys(tree: ChartTree): Set<string> {
  const keys = new Set<string>(tree.groups.filter((g) => g.accounts.length > 0).map(groupKey))
  if (tree.unplaced_cost_types.length > 0) keys.add(UNPLACED_KEY)
  return keys
}

function matches(query: string, code: string | null, name: string, description: string | null): boolean {
  return [code, name, description].some((value) => value !== null && value.toLowerCase().includes(query))
}

/**
 * Flattens the tree into the rows currently visible. With a non-blank `query`
 * only the matching entries and their ancestors appear, every ancestor open
 * (the caller's `expanded` set is ignored, so clearing the search brings back
 * exactly the previous expansion).
 */
export function buildRows(tree: ChartTree, expanded: ReadonlySet<string>, rawQuery: string): TreeRow[] {
  const query = rawQuery.trim().toLowerCase()
  const searching = query !== ''
  const rows: TreeRow[] = []

  for (const group of tree.groups) {
    const accountRows: TreeRow[] = []
    for (const account of group.accounts) {
      const subs = searching
        ? account.sub_accounts.filter((s) => matches(query, s.code, s.name, s.description))
        : account.sub_accounts
      const accountHit = searching && matches(query, account.code, account.name, account.description)
      if (searching && !accountHit && subs.length === 0) continue

      const open = searching ? subs.length > 0 : expanded.has(accountKey(account))
      accountRows.push({
        type: 'account',
        key: accountKey(account),
        depth: 1,
        node: account,
        group,
        expandable: account.sub_accounts.length > 0,
        expanded: open,
      })
      if (open) {
        for (const sub of subs) {
          accountRows.push({ type: 'sub', key: subKey(sub), depth: 2, node: sub, account, group })
        }
      }
    }

    const groupHit = searching && matches(query, group.code, group.name, group.description)
    if (searching && !groupHit && accountRows.length === 0) continue

    const open = searching ? accountRows.length > 0 : expanded.has(groupKey(group))
    rows.push({ type: 'group', key: groupKey(group), depth: 0, node: group, expandable: group.accounts.length > 0, expanded: open })
    if (open) rows.push(...accountRows)
  }

  const unplaced = searching
    ? tree.unplaced_cost_types.filter((s) => matches(query, s.code, s.name, s.description))
    : tree.unplaced_cost_types
  if (unplaced.length > 0) {
    const open = searching || expanded.has(UNPLACED_KEY)
    rows.push({ type: 'unplaced', key: UNPLACED_KEY, depth: 0, count: unplaced.length, expandable: true, expanded: open })
    if (open) {
      for (const sub of unplaced) {
        rows.push({ type: 'sub', key: subKey(sub), depth: 1, node: sub, account: null, group: null })
      }
    }
  }

  return rows
}

/** Select options for the parent account of a sub-account: the conti of the groups of that kind. */
export function accountOptions(groups: ChartGroup[], kind: ChartGroup['kind']): { value: string; label: string }[] {
  return groups
    .filter((group) => group.kind === kind)
    .flatMap((group) => group.accounts)
    .map((account) => ({ value: String(account.id), label: `${account.code} ${account.name}` }))
}
