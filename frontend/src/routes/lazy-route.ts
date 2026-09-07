import { lazy, type ComponentType, type LazyExoticComponent } from 'react'

/** Marks that a reload was already attempted, so it can happen at most once. */
const RELOAD_GUARD_KEY = 'app:chunk-reload'

/**
 * `React.lazy` for route modules, hardened against a deploy landing mid-session.
 *
 * A deploy replaces the content-hashed chunks: navigating to a route this
 * client had not loaded yet then rejects its dynamic import, and the user gets
 * a dead screen instead of the page. The only recovery is to load the new
 * build, so this reloads once. The sessionStorage guard is what keeps that
 * honest: a chunk that is broken for any other reason surfaces its error
 * instead of driving an endless reload loop.
 */
export function lazyRoute<P extends object>(
  factory: () => Promise<{ default: ComponentType<P> }>,
): LazyExoticComponent<ComponentType<P>> {
  return lazy(async () => {
    try {
      const module = await factory()
      clearReloadGuard()
      return module
    } catch (error) {
      if (!shouldReload()) {
        throw error
      }
      window.location.reload()
      // The document is being replaced: never resolve, so React keeps the
      // Suspense fallback up instead of flashing an error the user cannot act on.
      return new Promise<never>(() => {})
    }
  })
}

/**
 * Whether a reload may be attempted, arming the guard when it may. Storage can
 * throw (Safari private mode) or be unavailable; in that case no reload is
 * attempted, which fails towards the plain error rather than towards a loop.
 */
function shouldReload(): boolean {
  try {
    if (sessionStorage.getItem(RELOAD_GUARD_KEY) !== null) {
      return false
    }
    sessionStorage.setItem(RELOAD_GUARD_KEY, '1')
    return true
  } catch {
    return false
  }
}

function clearReloadGuard(): void {
  try {
    sessionStorage.removeItem(RELOAD_GUARD_KEY)
  } catch {
    // Nothing to clear when storage is unavailable.
  }
}
