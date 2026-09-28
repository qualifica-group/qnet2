import { useRef } from 'react'
import type { UseFormGetValues, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { useConfirm } from '@/components/confirm-dialog-context'
import { DEFAULT_MANAGER_SLOTS } from '@/features/opportunities/opportunity-schema'
import { fetchOpportunityRegistryMeta, type RegistryMeta } from '@/features/opportunities/opportunity-relation-meta'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import { managerSlotsFromRefs, padManagerSlots, sameManagerSlots } from '@/lib/utils'

/** The four form fields an anagrafica hands down to its opportunity (spec 0040 A-5, directive 2026-07-29). */
export interface InheritedRoles {
  commercial_id: number | null
  reporter_id: number | null
  supervisor_id: number | null
  manager_slots: (number | null)[]
}

type RoleKey = keyof InheritedRoles

const ROLE_KEYS: readonly RoleKey[] = ['commercial_id', 'reporter_id', 'supervisor_id', 'manager_slots']

export const NO_INHERITED_ROLES: InheritedRoles = {
  commercial_id: null,
  reporter_id: null,
  supervisor_id: null,
  manager_slots: [],
}

export function rolesFromRegistryMeta(meta: RegistryMeta | null): InheritedRoles {
  return {
    commercial_id: meta?.commercial?.id ?? null,
    reporter_id: meta?.reporter?.id ?? null,
    supervisor_id: meta?.supervisor?.id ?? null,
    manager_slots: managerSlotsFromRefs(meta?.managers ?? []),
  }
}

function isEmptyRole(roles: InheritedRoles, key: RoleKey): boolean {
  return key === 'manager_slots' ? roles.manager_slots.every((slot) => slot === null) : roles[key] === null
}

function sameRole(a: InheritedRoles, b: InheritedRoles, key: RoleKey): boolean {
  return key === 'manager_slots' ? sameManagerSlots(a.manager_slots, b.manager_slots) : a[key] === b[key]
}

/** A value the user entered: filled, and not what the previous anagrafica handed down. */
function isUserEntered(current: InheritedRoles, previous: InheritedRoles, key: RoleKey): boolean {
  return !isEmptyRole(current, key) && !sameRole(current, previous, key)
}

/** The user-entered roles that taking the new anagrafica's values would overwrite. */
export function conflictingUserRoles(
  current: InheritedRoles,
  previous: InheritedRoles,
  next: InheritedRoles,
): RoleKey[] {
  return ROLE_KEYS.filter((key) => isUserEntered(current, previous, key) && !sameRole(current, next, key))
}

/**
 * The roles after an anagrafica pick: its own values everywhere, except the
 * user-entered ones when the user chose to keep them.
 */
export function mergeInheritedRoles(
  current: InheritedRoles,
  previous: InheritedRoles,
  next: InheritedRoles,
  keepUserEntered: boolean,
): InheritedRoles {
  const keep = (key: RoleKey) => keepUserEntered && isUserEntered(current, previous, key)

  return {
    commercial_id: keep('commercial_id') ? current.commercial_id : next.commercial_id,
    reporter_id: keep('reporter_id') ? current.reporter_id : next.reporter_id,
    supervisor_id: keep('supervisor_id') ? current.supervisor_id : next.supervisor_id,
    // The four G.A. cards stay visible even for an anagrafica without
    // managers, exactly like the blank create form (directive 2026-07-29).
    manager_slots: keep('manager_slots')
      ? current.manager_slots
      : padManagerSlots(next.manager_slots, DEFAULT_MANAGER_SLOTS),
  }
}

/**
 * Picking an anagrafica resets the (anagrafica-scoped) referent and hands
 * down Commerciale, Segnalatore, Supervisore and the G.A. slots. The team
 * section sits ABOVE the client section, so those fields are often filled
 * before the anagrafica is chosen: a value the user entered is never
 * overwritten silently — a confirmation asks first. Values handed down by the
 * previous anagrafica are not the user's, so they are replaced without asking.
 */
export function useRegistryRoleInheritance(
  setValue: UseFormSetValue<OpportunityFormValues>,
  getValues: UseFormGetValues<OpportunityFormValues>,
) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const queryClient = useQueryClient()
  const inheritedRef = useRef<InheritedRoles>(NO_INHERITED_ROLES)
  const requestRef = useRef(0)

  return async (registryId: number | null) => {
    const request = ++requestRef.current
    // Step 1: the referent belongs to the previous anagrafica (BR-4).
    setValue('referent_id', null, { shouldDirty: true })

    // Step 2: the new anagrafica's roles; a later pick supersedes this one.
    const meta = registryId === null ? null : await fetchOpportunityRegistryMeta(queryClient, registryId)
    if (request !== requestRef.current) {
      return
    }
    const next = rolesFromRegistryMeta(meta)

    // Step 3: ask before overwriting anything the user entered.
    const current: InheritedRoles = {
      commercial_id: getValues('commercial_id'),
      reporter_id: getValues('reporter_id'),
      supervisor_id: getValues('supervisor_id'),
      manager_slots: getValues('manager_slots'),
    }
    const conflicts = conflictingUserRoles(current, inheritedRef.current, next)
    const replace =
      conflicts.length === 0 ||
      (await confirm({
        tone: 'warning',
        title: t('opportunities.form.registryRolesConfirm.title'),
        description: t('opportunities.form.registryRolesConfirm.description'),
        confirmLabel: t('opportunities.form.registryRolesConfirm.replace'),
        cancelLabel: t('opportunities.form.registryRolesConfirm.keep'),
      }))

    // Step 4: write the result and remember what this anagrafica handed down.
    const merged = mergeInheritedRoles(current, inheritedRef.current, next, !replace)
    for (const key of ROLE_KEYS) {
      setValue(key, merged[key], { shouldDirty: true })
    }
    inheritedRef.current = next
  }
}
