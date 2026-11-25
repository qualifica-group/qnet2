import { useAbilities } from '@/features/auth/use-abilities'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { PROJECT_WORK_ORDERS_DOMAIN } from '@/features/work-orders/project-work-order-module'

/**
 * Modules that can show a record of another domain, tried in order when the
 * actor may not view the record's own module. An Offerta is ALSO a Gestione
 * Richieste row (spec 0086 D-1: the grid rows are `quotes`), so the same id
 * opens there for an actor who works requests but not the Offerte module.
 * A Commessa link does not know the type: an actor who sees only "Commesse di
 * Progetto" gets that page, which sends a processing one back to "Commesse"
 * (2026-11-25).
 */
const VIEW_FALLBACK_DOMAINS: Readonly<Record<string, readonly string[]>> = {
  quotes: [REQUEST_MANAGEMENT_DOMAIN],
  'work-orders': [PROJECT_WORK_ORDERS_DOMAIN],
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
    candidates.find((candidate) => {
      const entry = getModuleRegistryEntry(candidate)

      return entry !== undefined && can(`${entry.permissionDomain ?? candidate}.view`)
    }) ?? null
  )
}

/** `resolveViewableDomain` for the current actor; `null` while abilities load (fail closed). */
export function useViewableDomain(domain: string): string | null {
  const { can } = useAbilities()

  return resolveViewableDomain(domain, can)
}
