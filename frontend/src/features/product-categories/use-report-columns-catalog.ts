import { useQuery } from '@tanstack/react-query'
import { fetchReportColumnsCatalog } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'

/**
 * Loads the statistics-column catalogue (spec 0141), one cache entry reused
 * by every category form/detail. `enabled` gates the request to when the
 * picker is actually shown (the category is effectively reportable), so a
 * category that never turns the report flag on never fetches it.
 */
export function useReportColumnsCatalog(enabled: boolean) {
  return useQuery({
    queryKey: productCategoryKeys.reportColumns,
    queryFn: fetchReportColumnsCatalog,
    enabled,
  })
}
