import { GENERAL_HELP_KEY } from '@/features/help/help-guide-keys'
import type { HelpVisibleGuide } from '@/features/help/help-visible-guides'

/**
 * Resolves the module guide for the current pathname (AC-002/AC-003): exact
 * route match or `route + '/'` prefix, same rule as `nav-main.tsx`'s
 * `isActive`. Among several matches (nested routes) the longest route wins,
 * mirroring `findNavItemByRoute`'s depth-first specificity. No match ->
 * `general`.
 */
export function resolveCurrentHelpGuideKey(
  guides: readonly HelpVisibleGuide[],
  pathname: string,
): string {
  let bestKey: string = GENERAL_HELP_KEY
  let bestRouteLength = -1

  for (const guide of guides) {
    if (guide.route === null) {
      continue
    }
    const isMatch = pathname === guide.route || pathname.startsWith(`${guide.route}/`)
    if (isMatch && guide.route.length > bestRouteLength) {
      bestKey = guide.key
      bestRouteLength = guide.route.length
    }
  }

  return bestKey
}
