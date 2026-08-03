import { describe, expect, it } from 'vitest'
import type { PermissionCatalogueArea, PermissionCatalogueResource } from '@/features/roles/permission-catalogue-api'
import {
  allPermissionNames,
  areaSelectionCount,
  areaSelectionState,
  resourceSelectionCount,
  resourceSelectionState,
  toggleAreaPermissions,
  togglePermission,
  toggleResourcePermissions,
} from '@/features/roles/permission-explorer/permission-selection'

const leads: PermissionCatalogueResource = {
  resource: 'leads',
  label_key: 'navigation.leads',
  permissions: [
    { name: 'leads.viewAny', ability: 'viewAny' },
    { name: 'leads.view', ability: 'view' },
  ],
  fields: [],
}

const opportunities: PermissionCatalogueResource = {
  resource: 'opportunities',
  label_key: 'navigation.opportunities',
  permissions: [{ name: 'opportunities.viewAny', ability: 'viewAny' }],
  fields: [],
}

const marketingArea: PermissionCatalogueArea = {
  key: 'marketing-leads',
  label_key: 'navigation.marketingLeads',
  resources: [leads, opportunities],
}

const emptyResource: PermissionCatalogueResource = {
  resource: 'notes',
  label_key: 'permissions.resources.notes',
  permissions: [],
  fields: [],
}

describe('resourceSelectionState', () => {
  it('is false with none selected', () => {
    expect(resourceSelectionState(leads, [])).toBe(false)
  })

  it('is indeterminate with a partial selection', () => {
    expect(resourceSelectionState(leads, ['leads.viewAny'])).toBe('indeterminate')
  })

  it('is true when every permission of the module is selected', () => {
    expect(resourceSelectionState(leads, ['leads.viewAny', 'leads.view'])).toBe(true)
  })

  it('is false for a module with no assignable permissions', () => {
    expect(resourceSelectionState(emptyResource, [])).toBe(false)
  })
})

describe('areaSelectionState', () => {
  it('is indeterminate when only one module is fully selected', () => {
    expect(areaSelectionState(marketingArea, ['leads.viewAny', 'leads.view'])).toBe('indeterminate')
  })

  it('is true only when every module of the area is fully selected', () => {
    expect(
      areaSelectionState(marketingArea, ['leads.viewAny', 'leads.view', 'opportunities.viewAny']),
    ).toBe(true)
  })

  it('is false with none selected', () => {
    expect(areaSelectionState(marketingArea, [])).toBe(false)
  })
})

describe('resourceSelectionCount / areaSelectionCount', () => {
  it('counts selected over total for a module', () => {
    expect(resourceSelectionCount(leads, ['leads.viewAny'])).toEqual({ selected: 1, total: 2 })
  })

  it('counts selected over total across every module of an area', () => {
    expect(areaSelectionCount(marketingArea, ['leads.viewAny', 'opportunities.viewAny'])).toEqual({
      selected: 2,
      total: 3,
    })
  })
})

describe('togglePermission', () => {
  it('adds the permission when checked', () => {
    expect(togglePermission(['leads.view'], 'leads.viewAny', true)).toEqual(['leads.view', 'leads.viewAny'])
  })

  it('removes the permission when unchecked', () => {
    expect(togglePermission(['leads.view', 'leads.viewAny'], 'leads.view', false)).toEqual(['leads.viewAny'])
  })
})

describe('toggleResourcePermissions', () => {
  it('grants every permission of the module, preserving unrelated ones', () => {
    expect(toggleResourcePermissions(leads, true, ['opportunities.viewAny'])).toEqual(
      expect.arrayContaining(['opportunities.viewAny', 'leads.viewAny', 'leads.view']),
    )
  })

  it('revokes only the module own permissions', () => {
    const current = ['leads.viewAny', 'leads.view', 'opportunities.viewAny']
    expect(toggleResourcePermissions(leads, false, current)).toEqual(['opportunities.viewAny'])
  })
})

describe('toggleAreaPermissions', () => {
  it('grants every permission of every module in the area, touching only that area', () => {
    const otherArea: PermissionCatalogueResource = {
      resource: 'users',
      label_key: 'navigation.users',
      permissions: [{ name: 'users.view', ability: 'view' }],
      fields: [],
    }
    const current = ['users.view']
    const next = toggleAreaPermissions(marketingArea, true, current)
    expect(next).toEqual(
      expect.arrayContaining(['users.view', 'leads.viewAny', 'leads.view', 'opportunities.viewAny']),
    )
    // Nothing from a resource outside the area is dropped.
    expect(next).toContain(otherArea.permissions[0]!.name)
  })

  it('revokes only the permissions belonging to that area', () => {
    const current = ['leads.viewAny', 'leads.view', 'opportunities.viewAny', 'users.view']
    expect(toggleAreaPermissions(marketingArea, false, current)).toEqual(['users.view'])
  })
})

describe('allPermissionNames', () => {
  it('flattens every permission name across every area and module', () => {
    expect(allPermissionNames([marketingArea])).toEqual([
      'leads.viewAny',
      'leads.view',
      'opportunities.viewAny',
    ])
  })

  it('returns an empty array for no areas', () => {
    expect(allPermissionNames([])).toEqual([])
  })
})
