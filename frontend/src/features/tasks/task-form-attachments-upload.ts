import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { TASK_ATTACHABLE_ALIAS } from '@/features/tasks/api'

/**
 * Uploads every staged file against the freshly created task, one request at
 * a time (spec 0118 D-7/AC-023) — mirrors `useAttachments`' own sequential
 * upload: the endpoint takes one file per request, and a burst of parallel
 * multipart bodies is what trips server upload limits. Never throws: a
 * rejected file is reported back by name so the caller can still navigate
 * (D-8) instead of trapping the user on a form for an already-saved task.
 */
export async function uploadStagedAttachments(taskId: number, files: File[]): Promise<string[]> {
  const failed: string[] = []
  for (const file of files) {
    try {
      await uploadAttachment({
        resource: TASK_ATTACHABLE_ALIAS,
        id: taskId,
        collection: DOCUMENTS_COLLECTION,
        file,
      })
    } catch {
      failed.push(file.name)
    }
  }
  return failed
}
