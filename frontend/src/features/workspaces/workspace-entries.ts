import type { NavigationCatalogueEntry } from '@/features/workspaces/types'

export interface EntryGroup {
  title: string
  entries: NavigationCatalogueEntry[]
}

const GROUP_SEPARATOR = ' › '

function normalize(text: string): string {
  return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
}

/**
 * Entries grouped by their ancestors, in catalogue (= menu) order. With a
 * query, an entry stays when its label or its group title matches it,
 * ignoring case and accents; groups left empty are dropped.
 */
export function groupEntries(
  catalogue: NavigationCatalogueEntry[],
  translate: (key: string) => string,
  query = '',
): EntryGroup[] {
  const needle = normalize(query)
  const groups = new Map<string, EntryGroup>()
  for (const entry of catalogue) {
    const title = entry.path.map(translate).join(GROUP_SEPARATOR)
    if (needle !== '' && !normalize(`${title} ${translate(entry.label)}`).includes(needle)) {
      continue
    }
    const group = groups.get(title) ?? { title, entries: [] }
    group.entries.push(entry)
    groups.set(title, group)
  }
  return [...groups.values()]
}

/** Adds (`checked`) or removes the given keys, keeping the rest of the selection untouched. */
export function toggleKeys(selection: string[], keys: string[], checked: boolean): string[] {
  if (checked) {
    return [...selection, ...keys.filter((key) => !selection.includes(key))]
  }
  return selection.filter((key) => !keys.includes(key))
}
