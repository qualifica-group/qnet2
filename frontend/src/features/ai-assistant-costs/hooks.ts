import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAiCostReport } from '@/features/ai-assistant-costs/api'
import { isValidPeriod } from '@/features/ai-assistant-costs/period'
import type { AiCostPeriod, AiCostReport } from '@/features/ai-assistant-costs/types'

export const aiCostKeys = {
  report: (period: AiCostPeriod) => ['ai-assistant-costs', 'report', period.from, period.to] as const,
}

/** Asks the server only for a period that can be answered (both dates, `from` not after `to`). */
export function useAiCostReport(period: AiCostPeriod) {
  return useQuery<AiCostReport, AxiosError>({
    queryKey: aiCostKeys.report(period),
    queryFn: () => fetchAiCostReport(period),
    enabled: isValidPeriod(period),
    placeholderData: keepPreviousData,
  })
}
