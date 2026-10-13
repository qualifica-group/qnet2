import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateCustomFieldDefinitionPayload,
  CustomFieldDefinitionDetail,
  CustomFieldDefinitionDetailWithPermissions,
  UpdateCustomFieldDefinitionPayload,
} from '@/features/custom-fields/types'

/** One custom-fieldable module, as listed by `GET /custom-fields/entities` (admin entity_type picker). */
export interface CustomFieldEntity {
  entity_type: string
  label: string
}

/** Populates the custom field form's host `entity_type` picker. */
export async function fetchCustomFieldEntities(): Promise<CustomFieldEntity[]> {
  const { data } = await apiClient.get<ApiResponse<CustomFieldEntity[]>>('/custom-fields/entities')
  return data.data
}

/**
 * Populates the `relation_target` pickers (custom fields and attributes): the
 * custom-fieldable modules plus the relation-only reference data such as
 * provinces (spec 0225), same item shape as `fetchCustomFieldEntities`.
 */
export async function fetchCustomFieldRelationTargets(): Promise<CustomFieldEntity[]> {
  const { data } = await apiClient.get<ApiResponse<CustomFieldEntity[]>>('/custom-fields/relation-targets')
  return data.data
}

/*
 * ---------------------------------------------------------------------------
 * ADMIN CRUD (spec 0021 — ADMIN PANEL): `CustomFieldDefinition` catalogue.
 * ---------------------------------------------------------------------------
 */

/** Query key for a single definition's detail (fresh-on-open pattern). */
export function customFieldDetailQueryKey(id: number) {
  return ['custom-fields', 'detail', id] as const
}

/**
 * Fetches a single custom field definition together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchCustomFieldDefinition(
  id: number,
): Promise<CustomFieldDefinitionDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<CustomFieldDefinitionDetail, ResourcePermissions>
  >(`/custom-fields/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a custom field definition. Returns the created resource from the envelope `data`. */
export async function createCustomFieldDefinition(
  payload: CreateCustomFieldDefinitionPayload,
): Promise<CustomFieldDefinitionDetail> {
  const { data } = await apiClient.post<ApiResponse<CustomFieldDefinitionDetail>>(
    '/custom-fields',
    payload,
  )
  return data.data
}

/** Partially updates a custom field definition (PATCH). Returns the updated resource with the actor's permissions, like `show`. */
export async function updateCustomFieldDefinition(
  id: number,
  payload: UpdateCustomFieldDefinitionPayload,
): Promise<CustomFieldDefinitionDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<CustomFieldDefinitionDetail, ResourcePermissions>
  >(`/custom-fields/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a custom field definition. Backend responds 204 with no body. */
export async function deleteCustomFieldDefinition(id: number): Promise<void> {
  await apiClient.delete(`/custom-fields/${id}`)
}
