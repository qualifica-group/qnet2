import { useQuery } from '@tanstack/react-query'
import { fetchStockBalances } from '@/features/stock/api'
import { stockKeys } from '@/features/stock/query-keys'
import type { StockBalanceFilters } from '@/features/stock/types'

/** The stock rows matching `filters`; `enabled: false` sends no request (an actor without `stock.viewAny`). */
export function useStockBalances(filters: StockBalanceFilters, enabled: boolean) {
  return useQuery({
    queryKey: stockKeys.balances(filters),
    queryFn: () => fetchStockBalances(filters),
    enabled,
  })
}
