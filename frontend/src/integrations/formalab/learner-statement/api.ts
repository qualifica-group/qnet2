import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition, saveBlob } from '@/lib/download'
import type {
  CommissionPayload,
  CommissionScenario,
  LearnerStatement,
  StatementExportVariant,
} from '@/integrations/formalab/learner-statement/types'

const base = (workOrderId: number) => `/integrations/formalab/work-orders/${workOrderId}/learner-statement`

const EXPORT_FALLBACK_NAME = 'prospetto_contabile.xlsx'

/** `scenario` null lets the server pick its default (financial once the balance is in). */
export async function fetchLearnerStatement(
  workOrderId: number,
  scenario: CommissionScenario | null,
  refresh = false,
): Promise<LearnerStatement> {
  const params = { ...(scenario ? { commission_scenario: scenario } : {}), ...(refresh ? { refresh: 1 } : {}) }
  const { data } = await apiClient.get<ApiResponse<LearnerStatement>>(base(workOrderId), { params })
  return data.data
}

export async function updateLearnerCommission(
  workOrderId: number,
  taxCode: string,
  payload: CommissionPayload,
  scenario: CommissionScenario,
): Promise<LearnerStatement> {
  const { data } = await apiClient.put<ApiResponse<LearnerStatement>>(
    `${base(workOrderId)}/learners/${encodeURIComponent(taxCode)}/commission`,
    { ...payload, commission_scenario: scenario },
  )
  return data.data
}

export async function updatePartnerSupplier(
  workOrderId: number,
  partner: string,
  supplierId: number | null,
  scenario: CommissionScenario,
): Promise<LearnerStatement> {
  const { data } = await apiClient.put<ApiResponse<LearnerStatement>>(`${base(workOrderId)}/partners/supplier`, {
    partner,
    supplier_id: supplierId,
    commission_scenario: scenario,
  })
  return data.data
}

/** Downloads the legacy Excel: the whole statement or one sheet per partner. */
export async function downloadLearnerStatement(
  workOrderId: number,
  variant: StatementExportVariant,
  scenario: CommissionScenario,
): Promise<void> {
  const response = await apiClient.get<Blob>(`${base(workOrderId)}/export`, {
    params: { variant, commission_scenario: scenario },
    responseType: 'blob',
  })
  saveBlob(response.data, filenameFromContentDisposition(response.headers['content-disposition']) ?? EXPORT_FALLBACK_NAME)
}
