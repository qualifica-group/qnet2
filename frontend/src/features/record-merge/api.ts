import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { MergeModule, MergePayload, MergePreview } from '@/features/record-merge/types'

/** Loads the merge preview of 2-5 records (spec 0394). */
export async function previewMerge(module: MergeModule, ids: number[]): Promise<MergePreview> {
  const { data } = await apiClient.post<ApiResponse<MergePreview>>(`/${module}/merge/preview`, { ids })
  return data.data
}

/** Merges the duplicates into the survivor; the server answers with the survivor's resource. */
export async function mergeRecords(module: MergeModule, payload: MergePayload): Promise<void> {
  await apiClient.post<ApiResponse<unknown>>(`/${module}/merge`, payload)
}
