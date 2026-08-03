import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'

/** One assignable permission of a module, in the server's canonical ability order. */
export interface PermissionCataloguePermission {
  name: string
  ability: string
}

/**
 * One field of a module's catalogue (native or custom, spec 0076). `label` is
 * the administrator-defined text for a custom field (`custom: true`), and
 * `null` for a native field — native labels are resolved client-side via i18n
 * (`fieldPermissionLabel`), see `catalogueFieldLabel`.
 */
export interface PermissionCatalogueField {
  key: string
  type: string
  group: string | null
  mandatory: boolean
  custom: boolean
  label: string | null
}

/** One module (resource) inside an area: its assignable permissions and fields. */
export interface PermissionCatalogueResource {
  resource: string
  label_key: string
  permissions: PermissionCataloguePermission[]
  fields: PermissionCatalogueField[]
}

/**
 * One area of the Role form's permission explorer tree — mirrors
 * `config/navigation.php`'s groups, plus a trailing `shared` area for
 * permission-only modules with no menu entry (`notes`, `attachments`).
 */
export interface PermissionCatalogueArea {
  key: string
  label_key: string
  resources: PermissionCatalogueResource[]
}

/** Response shape of `GET /authorization/permission-catalogue`, unwrapped from the envelope. */
export interface PermissionCatalogue {
  areas: PermissionCatalogueArea[]
}

/**
 * Fetches the Area > Module permission tree (spec 0076) backing the Role
 * form's two-panel explorer and the read-only role detail view. The taxonomy,
 * ordering and field catalogue (native + custom) are entirely server-built —
 * this client never derives a `resource -> area` mapping of its own.
 */
export async function fetchPermissionCatalogue(): Promise<PermissionCatalogue> {
  const { data } = await apiClient.get<ApiResponse<PermissionCatalogue>>(
    '/authorization/permission-catalogue',
  )
  return data.data
}
