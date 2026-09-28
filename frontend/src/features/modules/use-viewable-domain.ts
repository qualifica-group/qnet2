import { useAbilities } from '@/features/auth/use-abilities'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'

/**
 * Modules that can show a record of another domain, tried in order when the
 * actor may not view the record's own module. An Offerta is ALSO a Gestione
 * Richieste row (spec 0086 D-1: the grid rows are `quotes`), so the same id
 * opens there for an actor who works requests but not the Offerte module.
 */
const VIEW_FALLBACK_DOMAINS: Readonly<Record<string, readonly string[]>> = {
  quotes: [REQUEST_MANAGEMENT_DOMAIN],
}

/**
 * The registered module where a record of `domain` can be opened by an actor
 * holding `can`'s abilities: the domain itself, else its first permitted
 * fallback; `null` when none is viewable (user directive 2026-09-28: a record
 * the actor cannot see is never a link). The UI only hides: every detail
 * endpoint still authorizes server-side.
 */
export function resolveViewableDomain(domain: string, can: (permission: string) => boolean): string | null {
  const candidates = [domain, ...(VIEW_FALLBACK_DOMAINS[domain] ?? [])]

  return (
    candidates.find(
      (candidate) => getModuleRegistryEntry(candidate) !== undefined && can(`${candidate}.view`),
    ) ?? null
  )
}

/** `resolveViewableDomain` for the current actor; `null` while abilities load (fail closed). */
export function useViewableDomain(domain: string): string | null {
  const { can } = useAbilities()

  return resolveViewableDomain(domain, can)
}
