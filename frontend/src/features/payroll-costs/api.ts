import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { PayrollImportResult } from '@/features/payroll-costs/types'

/** `dryRun` previews summary and row errors without writing; false applies all-or-nothing. */
export async function importPayrollCosts(file: File, dryRun: boolean): Promise<PayrollImportResult> {
  const body = new FormData()
  body.append('file', file)
  body.append('dry_run', dryRun ? '1' : '0')
  const { data } = await apiClient.post<ApiResponse<PayrollImportResult>>('/payroll-costs/import', body)
  return data.data
}
