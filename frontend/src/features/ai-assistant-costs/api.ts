import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { AiCostPeriod, AiCostReport } from '@/features/ai-assistant-costs/types'

export async function fetchAiCostReport(period: AiCostPeriod): Promise<AiCostReport> {
  const { data } = await apiClient.get<ApiResponse<AiCostReport>>('/ai-assistant/costs', { params: period })
  return data.data
}
