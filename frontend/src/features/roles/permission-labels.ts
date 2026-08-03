import type { i18n as I18nInstance } from 'i18next'
import type { PermissionCatalogueField } from '@/features/roles/permission-catalogue-api'

/**
 * Translation helpers for permission names (e.g. `users.viewAny`). Shared by the
 * role form's permission explorer and the roles table's permissions column so
 * both render the same human-readable, localized labels. Each helper takes an
 * i18n instance (the hook's `i18n` in components, the singleton in AG Grid cell
 * renderers) and falls back to a humanized token when no translation exists, so
 * a newly added resource never shows a broken key.
 */

/** The ability suffix of a permission name, e.g. `users.view` → `view`. */
export function permissionAbility(permission: string): string {
  const dot = permission.indexOf('.')
  return dot === -1 ? permission : permission.slice(dot + 1)
}

/** `viewAny` → "View any", `audit_logs` → "Audit logs". */
function humanizeToken(token: string): string {
  return token
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/[-_]/g, ' ')
    .replace(/^\w/, (c) => c.toUpperCase())
}

/** Localized label for a resource prefix, e.g. `users` → "Utenti". */
export function resourceLabel(resource: string, i18n: I18nInstance): string {
  const key = `permissions.resources.${resource}`
  return i18n.exists(key) ? i18n.t(key) : humanizeToken(resource)
}

/** Localized label for a permission's ability suffix, e.g. `users.view` → "Visualizza". */
export function abilityLabel(permission: string, i18n: I18nInstance): string {
  const ability = permissionAbility(permission)
  const key = `permissions.abilities.${ability}`
  return i18n.exists(key) ? i18n.t(key) : humanizeToken(ability)
}

/**
 * Full localized label for a permission, e.g. `users.viewAny` →
 * "Utenti · Visualizza elenco". Permissions without a resource prefix render
 * just their ability label.
 */
export function permissionLabel(permission: string, i18n: I18nInstance): string {
  const dot = permission.indexOf('.')
  if (dot === -1) {
    return abilityLabel(permission, i18n)
  }
  return `${resourceLabel(permission.slice(0, dot), i18n)} · ${abilityLabel(permission, i18n)}`
}

/**
 * Localized label for a resource's field, e.g. `users`/`email` → "Email"
 * (spec 0006, field-permission matrix). Reuses each resource's existing form
 * field labels (`<resource>.form.<field>`) so the matrix shows the same names
 * as the form itself; falls back to a humanized token for a field with no
 * existing form label (e.g. a future resource with no dedicated form yet).
 */
export function fieldPermissionLabel(
  resource: string,
  field: string,
  i18n: I18nInstance,
): string {
  const key = `${resource}.form.${field}`
  return i18n.exists(key) ? i18n.t(key) : humanizeToken(field)
}

/**
 * Localized label for one entry of the permission catalogue's `fields[]`
 * (spec 0076): a custom field's label is the administrator's own free text
 * (`label`), a native field's label is the existing i18n form label
 * (`fieldPermissionLabel`). The contract guarantees `label` is set for every
 * custom entry (`PermissionCatalogueBuilder::customFieldLabels()`, AC-008,
 * covered by `PermissionCatalogueEndpointTest`), but `null` is still an
 * admitted value by that same contract (`$customLabels[...] ?? null`) — the
 * humanized-key fallback keeps the row readable (e.g. "Budget") instead of a
 * blank label, which a non-null assertion would silently render as empty.
 */
export function catalogueFieldLabel(
  resource: string,
  field: PermissionCatalogueField,
  i18n: I18nInstance,
): string {
  if (field.custom) {
    return field.label ?? humanizeToken(field.key)
  }
  return fieldPermissionLabel(resource, field.key, i18n)
}
