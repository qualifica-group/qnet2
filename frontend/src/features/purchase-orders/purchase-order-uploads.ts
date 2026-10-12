import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'
import { PURCHASE_ORDER_ATTACHABLE, type PurchaseOrder } from '@/features/purchase-orders/types'

/**
 * Uploads the files queued while the ODA did not exist yet onto the saved one
 * (the first, when copies were created: D-16). One request per file; returns
 * the names that failed so a partial failure never hides the saved record.
 */
export async function uploadOrderFiles(saved: PurchaseOrder, values: PurchaseOrderFormValues): Promise<string[]> {
  const failed: string[] = []
  for (const file of values.pending_files) {
    try {
      await uploadAttachment({
        resource: PURCHASE_ORDER_ATTACHABLE,
        id: saved.id,
        collection: DOCUMENTS_COLLECTION,
        file,
      })
    } catch {
      failed.push(file.name)
    }
  }
  return failed
}
