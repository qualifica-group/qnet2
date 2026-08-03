import type { i18n as I18nInstance } from 'i18next'
import type { PermissionCatalogueArea } from '@/features/roles/permission-catalogue-api'
import { abilityLabel, catalogueFieldLabel, resourceLabel } from '@/features/roles/permission-labels'

/**
 * Filters the permission catalogue tree for the explorer's search box
 * (spec 0076, AC-012/013/014): pure, no rendering. A module is kept — with
 * its full permission and field lists intact, never sub-filtered — the
 * moment ANY of its own label, one of its permission names/ability labels,
 * or one of its field labels (native or custom) contains the query. An area
 * with no matching module disappears entirely.
 */
export function filterPermissionCatalogue(
  areas: PermissionCatalogueArea[],
  query: string,
  i18n: I18nInstance,
): PermissionCatalogueArea[] {
  const needle = query.trim().toLowerCase()
  if (needle === '') {
    return areas
  }

  return areas
    .map((area) => ({
      ...area,
      resources: area.resources.filter((resource) => resourceMatches(resource.resource, needle, i18n)
        || resource.permissions.some((permission) => permissionMatches(permission, needle, i18n))
        || resource.fields.some((field) => catalogueFieldLabel(resource.resource, field, i18n).toLowerCase().includes(needle))),
    }))
    .filter((area) => area.resources.length > 0)
}

function resourceMatches(resource: string, needle: string, i18n: I18nInstance): boolean {
  return resourceLabel(resource, i18n).toLowerCase().includes(needle)
}

function permissionMatches(
  permission: { name: string; ability: string },
  needle: string,
  i18n: I18nInstance,
): boolean {
  return permission.name.toLowerCase().includes(needle)
    || abilityLabel(permission.name, i18n).toLowerCase().includes(needle)
}
