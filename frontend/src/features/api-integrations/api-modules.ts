import type { ParsedOperation } from '@/features/api-integrations/openapi-operations'
import type { NavigationItem } from '@/features/navigation/types'

export const OTHER_SECTION_KEY = 'other'
const ROOT_MODULE_KEY = 'root'

export interface ApiModule {
  /** First path segment of its operations (`leads`). */
  key: string
  basePath: string
  /** i18n key of the matching menu entry; null when no entry matches. */
  labelKey: string | null
  /** Label used when `labelKey` is null: the humanized segment. */
  fallbackLabel: string
  /** Icon name from the menu entry, null for the generic icon. */
  icon: string | null
  sectionKey: string
  /** i18n key of the menu section; null for the "Other" section. */
  sectionLabelKey: string | null
  operations: ParsedOperation[]
}

export interface ApiModuleSection {
  key: string
  labelKey: string | null
  modules: ApiModule[]
}

interface MenuEntry {
  item: NavigationItem
  section: NavigationItem | null
  order: number
}

function moduleKeyOf(path: string): string {
  return path.split('/')[1] || ROOT_MODULE_KEY
}

/** `work-order-payment-statuses` becomes `Work order payment statuses`. */
export function humanizeSegment(segment: string): string {
  const text = segment.replace(/[-_]+/g, ' ').trim()
  return text.charAt(0).toUpperCase() + text.slice(1)
}

/** Routed menu entries in menu order, each with the nearest unrouted ancestor as its section. */
function collectMenuEntries(items: NavigationItem[]): MenuEntry[] {
  const entries: MenuEntry[] = []
  const walk = (nodes: NavigationItem[], section: NavigationItem | null) => {
    for (const item of nodes) {
      if (item.route) {
        entries.push({ item, section, order: entries.length })
      }
      walk(item.children, item.route ? section : item)
    }
  }
  walk(items, null)
  return entries
}

function routeSegments(route: string): string[] {
  return route.split('/').filter((segment) => segment !== '')
}

/**
 * A module matches the entry whose route is exactly `/<segment>`, else the one
 * whose last segment is it (`/dev/api-clients`), else the first one starting
 * with it. The first entry in menu order wins within each rule.
 */
function indexMenuEntries(entries: MenuEntry[]): (moduleKey: string) => MenuEntry | undefined {
  const exact = new Map<string, MenuEntry>()
  const byLastSegment = new Map<string, MenuEntry>()
  const byFirstSegment = new Map<string, MenuEntry>()
  const remember = (index: Map<string, MenuEntry>, segment: string, entry: MenuEntry) => {
    if (!index.has(segment)) {
      index.set(segment, entry)
    }
  }
  for (const entry of entries) {
    const segments = routeSegments(entry.item.route ?? '')
    if (segments.length === 0) {
      continue
    }
    if (segments.length === 1) {
      remember(exact, segments[0], entry)
    }
    remember(byLastSegment, segments[segments.length - 1], entry)
    remember(byFirstSegment, segments[0], entry)
  }
  return (moduleKey) => exact.get(moduleKey) ?? byLastSegment.get(moduleKey) ?? byFirstSegment.get(moduleKey)
}

/**
 * Groups operations into modules (first path segment) and attaches the label,
 * icon and section of the matching menu entry; modules without one fall in the
 * "Other" section. Order: menu order, then the unmatched ones alphabetically.
 */
export function buildApiModules(
  operations: ParsedOperation[],
  navigation: NavigationItem[],
): ApiModule[] {
  const byModule = new Map<string, ParsedOperation[]>()
  for (const operation of operations) {
    const key = moduleKeyOf(operation.path)
    byModule.set(key, [...(byModule.get(key) ?? []), operation])
  }

  const findEntry = indexMenuEntries(collectMenuEntries(navigation))
  const ranked = [...byModule.entries()].map(([key, moduleOperations]) => {
    const entry = findEntry(key)
    const module: ApiModule = {
      key,
      basePath: key === ROOT_MODULE_KEY ? '/' : `/${key}`,
      labelKey: entry?.item.label ?? null,
      fallbackLabel: humanizeSegment(key),
      icon: entry?.item.icon ?? null,
      sectionKey: entry?.section?.key ?? OTHER_SECTION_KEY,
      sectionLabelKey: entry?.section?.label ?? null,
      operations: moduleOperations,
    }
    return { module, order: entry?.order ?? Number.POSITIVE_INFINITY }
  })

  return ranked
    .sort((a, b) => a.order - b.order || a.module.key.localeCompare(b.module.key))
    .map(({ module }) => module)
}

/** Sections in the order their first module appears, "Other" always last; modules keep their order. */
export function groupModulesBySection(modules: ApiModule[]): ApiModuleSection[] {
  const sections = new Map<string, ApiModuleSection>()
  for (const module of modules) {
    const section = sections.get(module.sectionKey) ?? {
      key: module.sectionKey,
      labelKey: module.sectionLabelKey,
      modules: [],
    }
    section.modules.push(module)
    sections.set(module.sectionKey, section)
  }
  const ordered = [...sections.values()]
  return [
    ...ordered.filter((section) => section.key !== OTHER_SECTION_KEY),
    ...ordered.filter((section) => section.key === OTHER_SECTION_KEY),
  ]
}
