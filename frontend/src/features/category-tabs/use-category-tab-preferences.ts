import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { fetchCategoryTabPreferences, saveCategoryTabPreferences } from '@/features/category-tabs/api'
import { levelArgs } from '@/features/category-tabs/category-tab-level'
import { categoryTabKeys } from '@/features/category-tabs/query-keys'
import type {
  CategoryTabLevel,
  CategoryTabPreferences,
  CategoryTabPreferencesPayload,
  CategoryTabSource,
} from '@/features/category-tabs/types'

/** What the strip shows until the preferences load, or when none were ever saved. */
const NO_PREFERENCES: CategoryTabPreferences = {
  favorite_category_ids: [],
  show_only_favorites: false,
  is_default: true,
}

/**
 * The actor's favourite strip categories, saved on the account (spec 0184).
 * Every change is optimistic — the star fills at once — and each write sends
 * the whole preference built on the cache, so quick successive clicks
 * accumulate instead of overwriting one another. A failed write restores the
 * previous state and says so. Each level (spec 0337) keeps its own favourites,
 * cache entry and server row.
 */
export function useCategoryTabPreferences({ key, apiBasePath }: CategoryTabSource, level: CategoryTabLevel = 'all') {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const queryKey = categoryTabKeys.preferences(key, level)

  const query = useQuery({
    queryKey,
    queryFn: () => fetchCategoryTabPreferences(apiBasePath, ...levelArgs(level)),
  })

  const mutation = useMutation({
    mutationKey: queryKey,
    mutationFn: (next: CategoryTabPreferencesPayload) => saveCategoryTabPreferences(apiBasePath, next, ...levelArgs(level)),
    onMutate: async (next) => {
      await queryClient.cancelQueries({ queryKey })
      const previous = queryClient.getQueryData<CategoryTabPreferences>(queryKey)
      // Saving makes the choice the actor's own, so the competence default no longer applies.
      queryClient.setQueryData<CategoryTabPreferences>(queryKey, { ...next, is_default: false })
      return { previous }
    },
    onError: (_error, _next, context) => {
      queryClient.setQueryData(queryKey, context?.previous)
      toast.error(t('categoryTabs.favoritesError'))
    },
    onSuccess: (saved) => {
      // An older answer must not overwrite a newer optimistic state still in flight.
      if (queryClient.isMutating({ mutationKey: queryKey }) <= 1) {
        queryClient.setQueryData(queryKey, saved)
      }
    },
  })

  const current = () => queryClient.getQueryData<CategoryTabPreferences>(queryKey) ?? NO_PREFERENCES

  const toggleFavorite = (categoryId: number) => {
    const { favorite_category_ids: ids, show_only_favorites } = current()
    mutation.mutate({
      favorite_category_ids: ids.includes(categoryId) ? ids.filter((id) => id !== categoryId) : [...ids, categoryId],
      show_only_favorites,
    })
  }

  const setShowOnlyFavorites = (showOnlyFavorites: boolean) => {
    mutation.mutate({ favorite_category_ids: current().favorite_category_ids, show_only_favorites: showOnlyFavorites })
  }

  return {
    preferences: query.data ?? NO_PREFERENCES,
    toggleFavorite,
    setShowOnlyFavorites,
  }
}
