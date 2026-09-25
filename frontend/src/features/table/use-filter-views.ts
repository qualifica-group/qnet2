import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createFilterView,
  deleteFilterView,
  favoriteFilterView,
  listFilterViews,
  unfavoriteFilterView,
  updateFilterView,
} from '@/features/table/filter-views-api'
import type { FilterViewInput } from '@/features/table/types'

/** Query keys for the saved filter views feature, namespaced by domain. */
export const filterViewKeys = {
  list: (domain: string) => ['table', domain, 'filter-views'] as const,
}

/**
 * Loads the actor's saved filter views for a domain (own + others' shared),
 * as listed by the backend (spec 0007).
 */
export function useFilterViews(domain: string) {
  return useQuery({
    queryKey: filterViewKeys.list(domain),
    queryFn: () => listFilterViews(domain),
  })
}

/**
 * Creates a new saved filter view for a domain. Invalidates the list so the
 * dropdown reflects the new view on its next read.
 */
export function useCreateFilterView(domain: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: FilterViewInput) => createFilterView(domain, input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: filterViewKeys.list(domain) })
    },
  })
}

/**
 * Replaces a saved filter view's fields (owner only). Invalidates the list on
 * success.
 */
export function useUpdateFilterView(domain: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: FilterViewInput }) =>
      updateFilterView(domain, id, input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: filterViewKeys.list(domain) })
    },
  })
}

/**
 * Deletes a saved filter view (owner only). Invalidates the list on success.
 */
export function useDeleteFilterView(domain: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => deleteFilterView(domain, id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: filterViewKeys.list(domain) })
    },
  })
}

/**
 * Toggles the actor's favorite flag on a view (spec 0158 D-4): stars when not
 * already favorite, unstars otherwise. Invalidates the list so the reordered
 * (favorites-first) response replaces it.
 */
export function useToggleFilterViewFavorite(domain: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, isFavorite }: { id: number; isFavorite: boolean }) =>
      isFavorite ? unfavoriteFilterView(domain, id) : favoriteFilterView(domain, id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: filterViewKeys.list(domain) })
    },
  })
}
