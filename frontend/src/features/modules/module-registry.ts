import type { ModuleRegistryEntry } from '@/features/modules/types'

/**
 * Single, AUTOMATIC source of truth of every switchable module (spec 0042).
 *
 * Any `features/<module>/<name>-screens.tsx` that exports a `moduleScreen`
 * descriptor is collected here at build time via `import.meta.glob` — there is
 * NO hand-maintained list. Adding a new module (or a new switchable one) needs
 * only its own adapter file: it then appears automatically in the settings
 * list, gets its generated deep-link routes, and becomes commutable modal/page.
 * `import-runs`/`migrations` simply never export a `moduleScreen` (non-CRUD,
 * out of scope).
 */
const adapters = import.meta.glob<{ moduleScreen?: ModuleRegistryEntry }>(
  '../*/*-screens.tsx',
  { eager: true },
)

export const MODULE_REGISTRY: readonly ModuleRegistryEntry[] = Object.values(adapters)
  .map((adapter) => adapter.moduleScreen)
  .filter((entry): entry is ModuleRegistryEntry => Boolean(entry))
  .sort((a, b) => a.domain.localeCompare(b.domain))

/** Looks up a registered module by its domain slug, or `undefined` if not (yet) registered. */
export function getModuleRegistryEntry(domain: string): ModuleRegistryEntry | undefined {
  return MODULE_REGISTRY.find((entry) => entry.domain === domain)
}

/**
 * Resolves an internal record path (`${basePath}/${id}`, e.g. a server-built
 * `subject_path`) back to its registered module and id, or `null` when no
 * registered module owns it — so a path-only link can still open as a modal.
 */
export function findModuleRecordByPath(path: string): { domain: string; id: number } | null {
  const match = /^(.+)\/(\d+)$/.exec(path)
  if (!match) {
    return null
  }
  const entry = MODULE_REGISTRY.find((candidate) => candidate.basePath === match[1])
  return entry ? { domain: entry.domain, id: Number(match[2]) } : null
}
