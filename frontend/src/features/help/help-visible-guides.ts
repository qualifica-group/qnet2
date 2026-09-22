import type { NavigationItem } from '@/features/navigation/types'
import { HELP_GUIDE_KEYS } from '@/features/help/help-guide-keys'

export interface HelpVisibleGuide {
  key: string
  label: string
  /** `null` only for the synthetic `general` entry, which has no menu route. */
  route: string | null
  /** Label of the nearest section/collapsible-group ancestor, or `null` when top-level. */
  groupLabel: string | null
}

/**
 * Flattens the (already permission-filtered) navigation tree into the guides
 * the current user may open (AC-004): only nodes whose `key` is one of the 50
 * authored guides and that carry a route become an entry, in tree order. A
 * `type: 'section'` node or a route-less parent with children (e.g.
 * `opportunities-group`) sets the group label inherited by its descendants;
 * a routed parent with children (e.g. `leads` -> `imports`) is itself an
 * entry and passes its OWN inherited group down unchanged, since it is a
 * module, not a group.
 */
export function flattenVisibleHelpGuides(
  items: NavigationItem[],
  translate: (key: string) => string,
): HelpVisibleGuide[] {
  const entries: HelpVisibleGuide[] = []
  walk(items, null, translate, entries)
  return entries
}

function walk(
  items: NavigationItem[],
  groupLabel: string | null,
  translate: (key: string) => string,
  entries: HelpVisibleGuide[],
): void {
  for (const item of items) {
    if (item.type === 'section') {
      walk(item.children, translate(item.label), translate, entries)
      continue
    }

    const isPureGroup = item.route === null && item.children.length > 0
    const childGroupLabel = isPureGroup ? translate(item.label) : groupLabel

    if (item.route && HELP_GUIDE_KEYS.includes(item.key)) {
      entries.push({
        key: item.key,
        label: translate(item.label),
        route: item.route,
        groupLabel,
      })
    }

    if (item.children.length > 0) {
      walk(item.children, childGroupLabel, translate, entries)
    }
  }
}

export interface HelpGuideGroup {
  label: string | null
  guides: HelpVisibleGuide[]
}

/**
 * Buckets an already-ordered guide list into consecutive runs sharing the
 * same `groupLabel`, for the index view (task 4: "conservando ordine e
 * gruppo di appartenenza"). Safe because `flattenVisibleHelpGuides` visits a
 * group's guides depth-first, so same-group entries are always adjacent.
 */
export function groupConsecutiveHelpGuides(
  guides: readonly HelpVisibleGuide[],
): HelpGuideGroup[] {
  const groups: HelpGuideGroup[] = []

  for (const guide of guides) {
    const lastGroup = groups[groups.length - 1]
    if (lastGroup && lastGroup.label === guide.groupLabel) {
      lastGroup.guides.push(guide)
    } else {
      groups.push({ label: guide.groupLabel, guides: [guide] })
    }
  }

  return groups
}
