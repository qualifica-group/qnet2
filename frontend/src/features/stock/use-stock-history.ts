import { useQuery } from '@tanstack/react-query'
import { fetchStockHistory } from '@/features/stock/api'
import { stockKeys } from '@/features/stock/query-keys'
import type { StockHistoryFilters } from '@/features/stock/types'

/** Movements of one stock row; idle while no row is open. */
export function useStockHistory(target: StockHistoryFilters | null) {
  return useQuery({
    queryKey: target === null ? stockKeys.all : stockKeys.history(target),
    queryFn: () => fetchStockHistory(target as StockHistoryFilters),
    enabled: target !== null,
  })
}
