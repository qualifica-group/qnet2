import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  StockBalance,
  StockBalanceFilters,
  StockHistoryFilters,
  StockMovement,
  StockMovementPayload,
  StockMovementResult,
} from '@/features/stock/types'

export async function recordStockMovement(payload: StockMovementPayload): Promise<StockMovementResult> {
  const { data } = await apiClient.post<ApiResponse<StockMovementResult>>('/stock-movements', payload)
  return data.data
}

export async function fetchStockBalances(params: StockBalanceFilters): Promise<StockBalance[]> {
  const { data } = await apiClient.get<ApiResponse<StockBalance[]>>('/stock-balances', { params })
  return data.data
}

export async function fetchStockHistory(params: StockHistoryFilters): Promise<StockMovement[]> {
  const { data } = await apiClient.get<ApiResponse<StockMovement[]>>('/stock-movements', { params })
  return data.data
}
