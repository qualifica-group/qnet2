import type { CategoryTabLevel } from '@/features/category-tabs/types'

/**
 * Query keys of the category strip, rooted on the source key. They are the
 * keys Gestione Richieste always used; the level (spec 0337) is the last
 * segment, so invalidating `categoriesPrefix` covers both levels.
 */
export const categoryTabKeys = {
  categoriesPrefix: (sourceKey: string) => [sourceKey, 'categories'] as const,
  categories: (sourceKey: string, level: CategoryTabLevel) => [sourceKey, 'categories', level] as const,
  preferences: (sourceKey: string, level: CategoryTabLevel) =>
    [sourceKey, 'category-tab-preferences', level] as const,
}
