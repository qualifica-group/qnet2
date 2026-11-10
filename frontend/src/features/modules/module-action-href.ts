import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import type { ActionHrefResolver } from '@/features/table/row-actions'

const VIEW_ACTION_KEY = 'view'
const DUPLICATE_ACTION_KEY = 'duplicate'

/**
 * The page a row action of a registered module leads to: `view` is the
 * record's detail page, `duplicate` its duplicate form (generated routes only;
 * a module with bespoke pages has no duplicate route). `undefined` for any
 * other action, or when `domain` is not a registered module.
 */
export function moduleActionHref(domain: string): ActionHrefResolver | undefined {
  const entry = getModuleRegistryEntry(domain)
  if (!entry) {
    return undefined
  }
  const { basePath } = entry
  const hasDuplicateRoute = entry.generateRoutes !== false
  return (action, row) => {
    if (action.key === VIEW_ACTION_KEY) {
      return `${basePath}/${row.id}`
    }
    if (action.key === DUPLICATE_ACTION_KEY && hasDuplicateRoute) {
      return `${basePath}/${row.id}/duplicate`
    }
    return undefined
  }
}

/**
 * The domain's own resolver first (pages the registry cannot know, e.g. an
 * offer's contract), then the module's `view`/`duplicate` pages.
 */
export function withModuleActionHref(
  domain: string,
  resolveActionHref?: ActionHrefResolver,
): ActionHrefResolver | undefined {
  const moduleHref = moduleActionHref(domain)
  if (!resolveActionHref) {
    return moduleHref
  }
  if (!moduleHref) {
    return resolveActionHref
  }
  return (action, row) => resolveActionHref(action, row) ?? moduleHref(action, row)
}
