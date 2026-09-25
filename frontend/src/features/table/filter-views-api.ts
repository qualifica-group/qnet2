import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { FilterViewInput, TableFilterView } from '@/features/table/types'

/**
 * Lists the actor's saved filter views for a domain (private + shared) plus
 * other users' `shared` views for the same domain (spec 0007). Order is owned
 * first, then shared-by-others, each group by name asc — set server-side.
 */
export async function listFilterViews(domain: string): Promise<TableFilterView[]> {
  const { data } = await apiClient.get<ApiResponse<TableFilterView[]>>(
    `/tables/${domain}/filter-views`,
  )
  return data.data
}

/**
 * Creates a new saved filter view owned by the actor. `filters` keys are
 * restricted server-side to the domain's filterable column ids.
 */
export async function createFilterView(
  domain: string,
  input: FilterViewInput,
): Promise<TableFilterView> {
  const { data } = await apiClient.post<ApiResponse<TableFilterView>>(
    `/tables/${domain}/filter-views`,
    input,
  )
  return data.data
}

/**
 * Replaces a saved filter view's fields. Owner only (enforced server-side via
 * `TableFilterViewPolicy`).
 */
export async function updateFilterView(
  domain: string,
  id: number,
  input: FilterViewInput,
): Promise<TableFilterView> {
  const { data } = await apiClient.put<ApiResponse<TableFilterView>>(
    `/tables/${domain}/filter-views/${id}`,
    input,
  )
  return data.data
}

/**
 * Deletes a saved filter view. Owner only (enforced server-side via
 * `TableFilterViewPolicy`).
 */
export async function deleteFilterView(domain: string, id: number): Promise<void> {
  await apiClient.delete(`/tables/${domain}/filter-views/${id}`)
}

/**
 * Stars a view as favorite for the actor (spec 0158 D-4), idempotent. Works
 * on any view visible to the actor (own or `shared`), not just owned ones.
 */
export async function favoriteFilterView(domain: string, id: number): Promise<TableFilterView> {
  const { data } = await apiClient.post<ApiResponse<TableFilterView>>(
    `/tables/${domain}/filter-views/${id}/favorite`,
  )
  return data.data
}

/** Unstars a view for the actor (spec 0158 D-4), idempotent. */
export async function unfavoriteFilterView(domain: string, id: number): Promise<TableFilterView> {
  const { data } = await apiClient.delete<ApiResponse<TableFilterView>>(
    `/tables/${domain}/filter-views/${id}/favorite`,
  )
  return data.data
}
