import type { NavigationItem } from '@/features/navigation/types'

/** A run of top-level entries sharing one sidebar group, or a labelled section. */
export type NavBlock =
  | { kind: 'items'; key: string; items: NavigationItem[] }
  | { kind: 'section'; section: NavigationItem }

/**
 * A route-less parent holding a single entry: a container with nothing to
 * choose inside, so it is not rendered collapsible (user rule 2026-10-27).
 * Typical inside an app, where the pruned menu leaves one child of a group.
 */
export function isSingleChildGroup(item: NavigationItem): boolean {
  return item.type !== 'section' && item.route === null && item.children.length === 1
}

/**
 * Top-level entries in menu order. Plain entries are batched into one group
 * per consecutive run; a section, and a single-child group read as one, each
 * get their own labelled group.
 */
export function buildNavBlocks(items: NavigationItem[]): NavBlock[] {
  const blocks: NavBlock[] = []
  for (const item of items) {
    if (item.type === 'section' || isSingleChildGroup(item)) {
      blocks.push({ kind: 'section', section: item })
      continue
    }
    const last = blocks.at(-1)
    if (last?.kind === 'items') {
      last.items.push(item)
    } else {
      blocks.push({ kind: 'items', key: item.key, items: [item] })
    }
  }
  return blocks
}
