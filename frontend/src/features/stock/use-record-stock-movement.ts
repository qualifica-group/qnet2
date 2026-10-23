import { useMutation, useQueryClient } from '@tanstack/react-query'
import { recordStockMovement } from '@/features/stock/api'
import { stockKeys } from '@/features/stock/query-keys'
import type { StockMovementPayload } from '@/features/stock/types'

/**
 * Registers a stock movement. On success the product and user sections (stock lists and the details that carry
 * the product's `stock_quantity`) are refreshed; the grids of the /stock page are refreshed by the caller.
 */
export function useRecordStockMovement() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: StockMovementPayload) => recordStockMovement(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: stockKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['products', 'detail'] })
    },
  })
}
