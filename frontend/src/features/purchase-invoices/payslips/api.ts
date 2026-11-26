import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { PayslipImportRequest, PayslipImportResult } from '@/features/purchase-invoices/payslips/types'

/** `dryRun` previews summary, errors and warnings without writing; false applies all-or-nothing. */
export async function importPayslips({ file, paymentDate }: PayslipImportRequest, dryRun: boolean): Promise<PayslipImportResult> {
  const body = new FormData()
  body.append('file', file)
  body.append('payment_date', paymentDate)
  body.append('dry_run', dryRun ? '1' : '0')
  const { data } = await apiClient.post<ApiResponse<PayslipImportResult>>('/purchase-invoices/payslips/import', body)
  return data.data
}
