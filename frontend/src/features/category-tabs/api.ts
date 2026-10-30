import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { levelRequestConfig } from '@/features/category-tabs/category-tab-level'
import type {
  CategoryTabItem,
  CategoryTabLevel,
  CategoryTabPreferences,
  CategoryTabPreferencesPayload,
} from '@/features/category-tabs/types'

/** A category as the grids' endpoint returns it (spec 0335). */
interface GridCategoryDto {
  id: number
  name: string
  records_count: number
}

/** A category as Gestione Richieste's endpoint returns it (spec 0064). */
export interface RequestsCategoryDto {
  id: number
  name: string
  requests_count: number
}

/** The only place the two count field names are reconciled into `count`. */
export function categoryTabItemsFromRequests(categories: RequestsCategoryDto[]): CategoryTabItem[] {
  return categories.map(({ id, name, requests_count }) => ({ id, name, count: requests_count }))
}

/** GET {basePath}/product-categories for the Opportunities and Quotes grids. */
export async function fetchGridCategoryTabs(
  basePath: string,
  level: CategoryTabLevel = 'all',
): Promise<CategoryTabItem[]> {
  const { data } = await apiClient.get<ApiResponse<{ categories: GridCategoryDto[] }>>(
    `${basePath}/product-categories`,
    levelRequestConfig(level),
  )
  return data.data.categories.map(({ id, name, records_count }) => ({ id, name, count: records_count }))
}

/** GET {basePath}/category-tab-preferences (spec 0184): the actor's favourite strip categories. */
export async function fetchCategoryTabPreferences(
  basePath: string,
  level: CategoryTabLevel = 'all',
): Promise<CategoryTabPreferences> {
  const { data } = await apiClient.get<ApiResponse<{ preferences: CategoryTabPreferences }>>(
    `${basePath}/category-tab-preferences`,
    levelRequestConfig(level),
  )
  return data.data.preferences
}

/** PUT {basePath}/category-tab-preferences (spec 0184): replaces the whole preference; only the two saved fields travel. */
export async function saveCategoryTabPreferences(
  basePath: string,
  preferences: CategoryTabPreferencesPayload,
  level: CategoryTabLevel = 'all',
): Promise<CategoryTabPreferences> {
  const { data } = await apiClient.put<ApiResponse<{ preferences: CategoryTabPreferences }>>(
    `${basePath}/category-tab-preferences`,
    {
      favorite_category_ids: preferences.favorite_category_ids,
      show_only_favorites: preferences.show_only_favorites,
    },
    levelRequestConfig(level),
  )
  return data.data.preferences
}
