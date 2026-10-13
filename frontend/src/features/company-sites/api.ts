import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CompanySiteDetail,
  CompanySiteDetailWithPermissions,
  CreateCompanySitePayload,
  UpdateCompanySitePayload,
} from '@/features/company-sites/types'

/** Query key of a single company site's detail, shared by the detail screen and its in-place saves. */
export function companySiteDetailQueryKey(id: number) {
  return ['company-sites', 'detail', id] as const
}

/**
 * Fetches a single company-site detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchCompanySite(id: number): Promise<CompanySiteDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<CompanySiteDetail, ResourcePermissions>
  >(`/company-sites/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a company site. Returns the created resource from the envelope `data`. */
export async function createCompanySite(
  payload: CreateCompanySitePayload,
): Promise<CompanySiteDetail> {
  const { data } = await apiClient.post<ApiResponse<CompanySiteDetail>>(
    '/company-sites',
    payload,
  )
  return data.data
}

/** What every write on an existing site answers: the saved record with the actor's permissions. */
async function saved(
  request: Promise<{ data: ApiResponseWithPermissions<CompanySiteDetail, ResourcePermissions> }>,
): Promise<CompanySiteDetailWithPermissions> {
  const { data } = await request
  return { ...data.data, permissions: data.permissions }
}

type SavedEnvelope = ApiResponseWithPermissions<CompanySiteDetail, ResourcePermissions>

/**
 * Partially updates a company site (PATCH). Returns the saved record with the
 * actor's permissions, so the in-place detail re-syncs on it as is.
 */
export function updateCompanySite(
  id: number,
  payload: UpdateCompanySitePayload,
): Promise<CompanySiteDetailWithPermissions> {
  return saved(apiClient.patch<SavedEnvelope>(`/company-sites/${id}`, payload))
}

/** Deletes a company site. Backend responds 204 with no body. */
export async function deleteCompanySite(id: number): Promise<void> {
  await apiClient.delete(`/company-sites/${id}`)
}

/**
 * Sets this site as the company's default one. Server-side, in a single
 * transaction, every other site's `is_default` is cleared (spec 0020: at
 * most one default site at a time).
 */
export function setDefaultCompanySite(id: number): Promise<CompanySiteDetailWithPermissions> {
  return saved(apiClient.post<SavedEnvelope>(`/company-sites/${id}/set-default`))
}

/** Uploads (replacing any previous) logo for an existing company site. */
export function uploadCompanySiteLogo(id: number, file: File): Promise<CompanySiteDetailWithPermissions> {
  const formData = new FormData()
  formData.append('logo', file)
  return saved(apiClient.post<SavedEnvelope>(`/company-sites/${id}/logo`, formData))
}

/** Removes the current logo of an existing company site. */
export function deleteCompanySiteLogo(id: number): Promise<CompanySiteDetailWithPermissions> {
  return saved(apiClient.delete<SavedEnvelope>(`/company-sites/${id}/logo`))
}
