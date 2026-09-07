import { useEffect, useRef } from 'react'
import type { UseFormReturn } from 'react-hook-form'
import { useAbilities } from '@/features/auth/use-abilities'
import { useAuth } from '@/features/auth/use-auth'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import { OPERATOR_MANAGER_POSITION } from '@/features/request-management/types'

/**
 * Supervisory ability gating the team block (mirrors
 * RequestManagementPolicy::assignOperator). Owned here, next to the default it
 * gates: the same ability decides whether the control is offered AND whether
 * its value may travel in the payload, and the two must never drift apart.
 */
export const ASSIGN_OPERATOR_PERMISSION = 'request-management.assignOperator'

/**
 * Ability gating the Sede operativa field: the same one
 * RequestManagementAuthorization hangs the `operational_site_id` ceiling off,
 * and the same one the `/operational-sites/for-select` picker needs to answer
 * at all — without it the control would render onto a 403.
 */
export const OPERATIONAL_SITES_VIEW_ANY_PERMISSION = 'operational-sites.viewAny'

/**
 * Opens the create form's operator slot and Sede operativa on the connected
 * actor and the actor's own Sede (user directive 2026-08-04): a request is
 * worked by whoever opened it, from the Sede they belong to. Since spec 0097
 * the destination is the team's OPERATOR_MANAGER_POSITION card rather than a
 * field of its own — the seeded VALUE is unchanged, only where it lands.
 *
 * This is the VISIBLE half of that rule only. The authority is
 * RequestCreationService, which applies the SAME default server-side to a
 * payload that omits the two keys — which is what covers an actor who never
 * sees the fields. That actor may not submit them either (the store endpoint
 * answers 403), hence the ability gate here: seeding a value they are not
 * entitled to send would break the save instead of prefilling it.
 *
 * An effect, not `defaultValues`: the abilities map that decides whether each
 * field is rendered can resolve after the form is built. Each field is seeded
 * at most once (the refs), so a pick made afterwards is never overwritten.
 */
export function useRequestActorAttributionDefaults(form: UseFormReturn<RequestCreateFormValues>): void {
  const { user } = useAuth()
  const { can } = useAbilities()
  const actorSiteId = user?.employment?.primary_operational_site_id ?? null
  const seededOperatorRef = useRef(false)
  const seededSiteRef = useRef(false)

  useEffect(() => {
    if (!seededOperatorRef.current && user !== null && can(ASSIGN_OPERATOR_PERMISSION)) {
      seededOperatorRef.current = true
      // Positional write: every other slot keeps whatever the form opened on.
      form.setValue(
        'manager_slots',
        form
          .getValues('manager_slots')
          .map((slot, index) => (index + 1 === OPERATOR_MANAGER_POSITION ? user.id : slot)),
      )
    }
    if (!seededSiteRef.current && actorSiteId !== null && can(OPERATIONAL_SITES_VIEW_ANY_PERMISSION)) {
      seededSiteRef.current = true
      form.setValue('operational_site_id', actorSiteId)
    }
  }, [user, actorSiteId, can, form])
}
