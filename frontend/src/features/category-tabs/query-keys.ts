/**
 * Query keys of the category strip, rooted on the source key. They are the
 * keys Gestione Richieste always used, so its cache entries do not move.
 */
export const categoryTabKeys = {
  categories: (sourceKey: string) => [sourceKey, 'categories'] as const,
  preferences: (sourceKey: string) => [sourceKey, 'category-tab-preferences'] as const,
}
