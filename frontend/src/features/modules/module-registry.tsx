import { Suspense, type ComponentType } from 'react'
import { DetailLoading } from '@/components/detail/detail-panel'
import { lazyRoute } from '@/routes/lazy-route'
import type { ModuleDefinition, ModuleRegistryEntry, ModuleScreens } from '@/features/modules/types'

/**
 * Single, AUTOMATIC source of truth of every switchable module (spec 0042).
 *
 * Each module is a pair of files collected at build time via `import.meta.glob`
 * — there is NO hand-maintained list:
 * - `features/<module>/<name>-module.ts` exports the `moduleDefinition`
 *   (domain, paths, open mode, flags): small, loaded with the app shell;
 * - `features/<module>/<name>-screens.tsx` exports the `moduleScreens`
 *   (`DetailScreen`/`FormScreen`): loaded only when a screen first renders,
 *   so the modules' code stays out of the initial bundle.
 * `import-runs`/`migrations` simply never export a definition (non-CRUD,
 * out of scope).
 */
const definitions = import.meta.glob<ModuleDefinition>('../*/*-module.ts', {
  eager: true,
  import: 'moduleDefinition',
})
const screenLoaders = import.meta.glob<ModuleScreens>('../*/*-screens.tsx', { import: 'moduleScreens' })

/**
 * A screen that loads its module chunk on first render. It suspends inside
 * its own boundary, showing the detail skeleton, so the hosts (page, Sheet)
 * stay mounted while the chunk downloads.
 */
function lazyScreen<P extends object>(
  load: () => Promise<ModuleScreens>,
  pick: (screens: ModuleScreens) => ComponentType<P>,
): ComponentType<P> {
  const Screen = lazyRoute(async () => ({ default: pick(await load()) }))
  return function LazyModuleScreen(props: P) {
    return (
      <Suspense fallback={<DetailLoading />}>
        <Screen {...props} />
      </Suspense>
    )
  }
}

function toRegistryEntry([path, definition]: [string, ModuleDefinition]): ModuleRegistryEntry {
  const load = screenLoaders[path.replace(/-module\.ts$/, '-screens.tsx')]
  if (!load) {
    throw new Error(`Module registry: "${path}" has no sibling *-screens.tsx exporting moduleScreens.`)
  }
  return {
    ...definition,
    DetailScreen: lazyScreen(load, (screens) => screens.DetailScreen),
    FormScreen: lazyScreen(load, (screens) => screens.FormScreen),
  }
}

export const MODULE_REGISTRY: readonly ModuleRegistryEntry[] = Object.entries(definitions)
  .map(toRegistryEntry)
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
