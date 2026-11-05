/** Grouping shared by the cost and revenue sub-account pickers (spec 0350 D-10 / D-12). */

export interface SubAccountNode {
  code: string
  name: string
}

/** Where a sub-account sits in the chart; both null/absent = not placed yet. */
export interface SubAccountPlacement {
  id: number
  group?: SubAccountNode | null
  account?: SubAccountNode | null
}

/** A conto with its selectable sub-accounts; an empty heading means "no conto level". */
export interface SubAccountAccountGroup<T> {
  key: string
  heading: string
  options: T[]
}

/** A mastro with its conti. */
export interface SubAccountGroup<T> {
  key: string
  heading: string
  accounts: SubAccountAccountGroup<T>[]
}

const UNPLACED_KEY = '__unplaced__'

/** Option text: code first, so the list reads like the chart of accounts; name only without a code. */
export function subAccountLabel(option: { code?: string | null; name: string }): string {
  return option.code ? `${option.code} ${option.name}` : option.name
}

/**
 * Groups options by mastro > conto keeping the server order (natural code order).
 * Options without a conto go to a trailing flat group headed `unplacedHeading`.
 */
export function groupSubAccounts<T extends SubAccountPlacement>(
  options: readonly T[],
  unplacedHeading: string,
): SubAccountGroup<T>[] {
  const groups: SubAccountGroup<T>[] = []
  const unplaced: T[] = []
  for (const option of options) {
    const { group: mastro, account: conto } = option
    if (!mastro || !conto) {
      unplaced.push(option)
      continue
    }
    let group = groups.find((candidate) => candidate.key === mastro.code)
    if (!group) {
      group = { key: mastro.code, heading: `${mastro.code} ${mastro.name}`, accounts: [] }
      groups.push(group)
    }
    let account = group.accounts.find((candidate) => candidate.key === conto.code)
    if (!account) {
      account = { key: conto.code, heading: `${conto.code} ${conto.name}`, options: [] }
      group.accounts.push(account)
    }
    account.options.push(option)
  }
  if (unplaced.length > 0) {
    groups.push({
      key: UNPLACED_KEY,
      heading: unplacedHeading,
      accounts: [{ key: UNPLACED_KEY, heading: '', options: unplaced }],
    })
  }
  return groups
}
