import type { StockBalanceFilters, StockHistoryFilters } from '@/features/stock/types'

/** Centralized query keys of the stock feature (spec 0307). */
export const stockKeys = {
  all: ['stock'] as const,
  balances: (filters: StockBalanceFilters) => ['stock', 'balances', filters] as const,
  history: (filters: StockHistoryFilters) => ['stock', 'history', filters] as const,
}
