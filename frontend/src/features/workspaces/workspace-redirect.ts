import type { AvailableWorkspaces } from '@/features/workspaces/types'

export type UnprefixedDecision =
  | { kind: 'stay' }
  | { kind: 'redirect'; slug: string }
  | { kind: 'empty' }

/**
 * What to do with a URL that carries no app prefix (spec 0317 D-7, D-8): enter
 * the remembered app when it is still available; otherwise stay on the full
 * view when allowed, or fall back to the first available app.
 */
export function decideUnprefixed(
  available: AvailableWorkspaces,
  remembered: string | null,
): UnprefixedDecision {
  if (remembered !== null && available.workspaces.some((workspace) => workspace.slug === remembered)) {
    return { kind: 'redirect', slug: remembered }
  }
  if (available.full_view_available) {
    return { kind: 'stay' }
  }
  const first = available.workspaces[0]
  return first ? { kind: 'redirect', slug: first.slug } : { kind: 'empty' }
}
