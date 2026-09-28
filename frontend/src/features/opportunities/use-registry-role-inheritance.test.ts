import { describe, expect, it } from 'vitest'
import {
  conflictingUserRoles,
  mergeInheritedRoles,
  NO_INHERITED_ROLES,
  rolesFromRegistryMeta,
  type InheritedRoles,
} from '@/features/opportunities/use-registry-role-inheritance'

const REGISTRY_ROLES: InheritedRoles = {
  commercial_id: 71,
  reporter_id: 81,
  supervisor_id: 61,
  manager_slots: [91, null, 93],
}

describe('rolesFromRegistryMeta', () => {
  it('maps the anagrafica meta onto the form fields, managers gap-aware by position', () => {
    expect(
      rolesFromRegistryMeta({
        commercial: { id: 71, name: 'Sara' },
        reporter: { id: 81, name: 'Elio' },
        supervisor: { id: 61, name: 'Ivo' },
        managers: [
          { id: 91, name: 'Gina', position: 1 },
          { id: 93, name: 'Turi', position: 3 },
        ],
      }),
    ).toEqual(REGISTRY_ROLES)
  })

  it('yields no roles when the meta is missing', () => {
    expect(rolesFromRegistryMeta(null)).toEqual(NO_INHERITED_ROLES)
  })
})

describe('conflictingUserRoles', () => {
  it('reports nothing on an empty form', () => {
    const current = { ...NO_INHERITED_ROLES, manager_slots: [null, null, null, null] }

    expect(conflictingUserRoles(current, NO_INHERITED_ROLES, REGISTRY_ROLES)).toEqual([])
  })

  it('reports the values the user entered that the anagrafica would change', () => {
    const current = { ...NO_INHERITED_ROLES, supervisor_id: 5, manager_slots: [null, 6, null, null] }

    expect(conflictingUserRoles(current, NO_INHERITED_ROLES, REGISTRY_ROLES)).toEqual([
      'supervisor_id',
      'manager_slots',
    ])
  })

  it('ignores a user value equal to the anagrafica one, trailing empty slots included', () => {
    const current = { ...NO_INHERITED_ROLES, supervisor_id: 61, manager_slots: [91, null, 93, null] }

    expect(conflictingUserRoles(current, NO_INHERITED_ROLES, REGISTRY_ROLES)).toEqual([])
  })

  it('ignores the values handed down by the previous anagrafica', () => {
    expect(conflictingUserRoles(REGISTRY_ROLES, REGISTRY_ROLES, NO_INHERITED_ROLES)).toEqual([])
  })
})

describe('mergeInheritedRoles', () => {
  const current = { ...NO_INHERITED_ROLES, supervisor_id: 5, manager_slots: [null, 6, null, null] }

  it('keeps the user values and inherits the rest when keeping', () => {
    expect(mergeInheritedRoles(current, NO_INHERITED_ROLES, REGISTRY_ROLES, true)).toEqual({
      commercial_id: 71,
      reporter_id: 81,
      supervisor_id: 5,
      manager_slots: [null, 6, null, null],
    })
  })

  it('takes every anagrafica value when replacing, padding the G.A. slots to the default four', () => {
    expect(mergeInheritedRoles(current, NO_INHERITED_ROLES, REGISTRY_ROLES, false)).toEqual({
      ...REGISTRY_ROLES,
      manager_slots: [91, null, 93, null],
    })
  })

  it('leaves four empty G.A. slots for an anagrafica without managers', () => {
    expect(mergeInheritedRoles(REGISTRY_ROLES, REGISTRY_ROLES, NO_INHERITED_ROLES, true).manager_slots).toEqual([
      null,
      null,
      null,
      null,
    ])
  })
})
