import { useEffect, useRef } from 'react'
import type { UseFormGetValues, UseFormSetValue } from 'react-hook-form'
import { useRegistryRoleInheritance } from '@/features/opportunities/use-registry-role-inheritance'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/**
 * Spec 0199: a create opened on an anagrafica (its detail's "Nuova
 * opportunita'") hands down that anagrafica's Commerciale, Segnalatore,
 * Supervisore and G.A. exactly like picking it by hand
 * (`useRegistryRoleInheritance`). The anagrafica itself is already in the
 * default values; this runs ONCE, so a later edit is never overwritten.
 */
export function useOpportunityRegistryPreset(
  registryId: number | undefined,
  setValue: UseFormSetValue<OpportunityFormValues>,
  getValues: UseFormGetValues<OpportunityFormValues>,
): void {
  const applyRegistrySelection = useRegistryRoleInheritance(setValue, getValues)
  const applied = useRef(false)

  useEffect(() => {
    if (applied.current || registryId === undefined) {
      return
    }
    applied.current = true
    void applyRegistrySelection(registryId)
  }, [registryId, applyRegistrySelection])
}
