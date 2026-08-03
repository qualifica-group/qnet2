import type {
  PermissionCatalogueArea,
  PermissionCatalogueResource,
} from '@/features/roles/permission-catalogue-api'

/** A checkbox's tri-state value (spec 0076, AC-015): Radix's own `checked` union. */
export type TriState = boolean | 'indeterminate'

export interface SelectionCount {
  selected: number
  total: number
}

/** Every permission name belonging to one module. */
function resourcePermissionNames(resource: PermissionCatalogueResource): string[] {
  return resource.permissions.map((permission) => permission.name)
}

/** Every permission name belonging to any module of one area. */
function areaPermissionNames(area: PermissionCatalogueArea): string[] {
  return area.resources.flatMap(resourcePermissionNames)
}

/** Every permission name in the whole catalogue — drives the global select-all. */
export function allPermissionNames(areas: PermissionCatalogueArea[]): string[] {
  return areas.flatMap(areaPermissionNames)
}

function triState(names: string[], selected: string[]): TriState {
  if (names.length === 0) {
    return false
  }
  const set = new Set(selected)
  const selectedCount = names.filter((name) => set.has(name)).length
  if (selectedCount === 0) {
    return false
  }
  return selectedCount === names.length ? true : 'indeterminate'
}

function count(names: string[], selected: string[]): SelectionCount {
  const set = new Set(selected)
  return { selected: names.filter((name) => set.has(name)).length, total: names.length }
}

/** Tri-state of one module's checkbox: `checked` only when every one of its abilities is granted. */
export function resourceSelectionState(resource: PermissionCatalogueResource, selected: string[]): TriState {
  return triState(resourcePermissionNames(resource), selected)
}

/** Tri-state of one area's checkbox: `checked` only when every module of the area is fully granted. */
export function areaSelectionState(area: PermissionCatalogueArea, selected: string[]): TriState {
  return triState(areaPermissionNames(area), selected)
}

/** `selected / total` count for one module, e.g. the tree row's badge. */
export function resourceSelectionCount(resource: PermissionCatalogueResource, selected: string[]): SelectionCount {
  return count(resourcePermissionNames(resource), selected)
}

/** `selected / total` count for one area, e.g. the area header's badge. */
export function areaSelectionCount(area: PermissionCatalogueArea, selected: string[]): SelectionCount {
  return count(areaPermissionNames(area), selected)
}

/** Adds or removes one permission name from the flat selection (a single ability toggle). */
export function togglePermission(current: string[], name: string, checked: boolean): string[] {
  return checked ? [...current, name] : current.filter((value) => value !== name)
}

function toggleNames(current: string[], names: string[], checked: boolean): string[] {
  if (checked) {
    const set = new Set(current)
    names.forEach((name) => set.add(name))
    return Array.from(set)
  }
  const removed = new Set(names)
  return current.filter((name) => !removed.has(name))
}

/**
 * Toggling a module's checkbox grants/revokes ALL of that module's abilities,
 * touching only its own permission names (AC-016's module-scoped analogue).
 */
export function toggleResourcePermissions(
  resource: PermissionCatalogueResource,
  checked: boolean,
  current: string[],
): string[] {
  return toggleNames(current, resourcePermissionNames(resource), checked)
}

/**
 * Toggling an area's checkbox grants/revokes every permission of every module
 * in that area, and ONLY that area (AC-016): other areas are left untouched.
 */
export function toggleAreaPermissions(
  area: PermissionCatalogueArea,
  checked: boolean,
  current: string[],
): string[] {
  return toggleNames(current, areaPermissionNames(area), checked)
}
