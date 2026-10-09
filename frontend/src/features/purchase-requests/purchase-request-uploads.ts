import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import {
  PURCHASE_REQUEST_ATTACHABLE,
  PURCHASE_REQUEST_LINE_ATTACHABLE,
  type PurchaseRequest,
} from '@/features/purchase-requests/types'

interface UploadJob {
  resource: string
  id: number
  file: File
}

/**
 * Files queued while the owner did not exist yet (D-15), mapped to their saved
 * owner. A line already persisted keeps its id; a new line is matched to the
 * saved line at the same `position` order (the payload keeps the form order).
 */
function collectJobs(saved: PurchaseRequest, values: PurchaseRequestFormValues): UploadJob[] {
  const savedLines = [...saved.lines].sort((a, b) => a.position - b.position)
  const requestJobs = values.pending_files.map((file) => ({
    resource: PURCHASE_REQUEST_ATTACHABLE,
    id: saved.id,
    file,
  }))
  const lineJobs = values.lines.flatMap((line, index) => {
    const lineId = line.id ?? savedLines[index]?.id
    return lineId === undefined
      ? []
      : line.pending_files.map((file) => ({ resource: PURCHASE_REQUEST_LINE_ATTACHABLE, id: lineId, file }))
  })
  return [...requestJobs, ...lineJobs]
}

/**
 * Uploads the queued files one by one (the endpoint takes a single file per
 * request) and returns the names that failed, so a partial failure never
 * hides the saved record.
 */
export async function uploadQueuedFiles(saved: PurchaseRequest, values: PurchaseRequestFormValues): Promise<string[]> {
  const failed: string[] = []
  for (const job of collectJobs(saved, values)) {
    try {
      await uploadAttachment({ resource: job.resource, id: job.id, collection: DOCUMENTS_COLLECTION, file: job.file })
    } catch {
      failed.push(job.file.name)
    }
  }
  return failed
}
