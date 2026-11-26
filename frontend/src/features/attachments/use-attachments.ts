import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  attachmentsQueryKey,
  deleteAttachment,
  listAttachments,
  uploadAttachment,
} from '@/features/attachments/api'
import type { Attachment, AttachmentList } from '@/features/attachments/types'

/** Stable empty array: avoids a new reference (and a rerender trigger) while the list query has no data yet. */
const EMPTY_DOCUMENTS: Attachment[] = []

/** One file of a batch the server refused, with the request error that says why. */
export interface FailedUpload {
  name: string
  error: unknown
}

/**
 * Outcome of a multi-file upload: the API takes one file per request, so a
 * batch can succeed partially. The caller reports the failed files (and the
 * reason of each) instead of losing the files that did upload.
 */
export interface UploadBatchResult {
  uploaded: number
  failed: FailedUpload[]
}

/**
 * The document list of one owner's collection. Shared by `useAttachments` and
 * the tab count (`DocumentsTabCount`): same key, one request.
 */
export function useAttachmentList(resource: string, id: number, collection: string) {
  return useQuery<AttachmentList, AxiosError>({
    queryKey: attachmentsQueryKey(resource, id, collection),
    queryFn: () => listAttachments(resource, id, collection),
  })
}

/**
 * Self-fetching document list for a polymorphic owner (`resource`/`id`),
 * scoped to a single `collection`. Upload and delete invalidate the same
 * list query key on success, so the grid stays in sync without any manual
 * cache bookkeeping.
 */
export function useAttachments(resource: string, id: number, collection: string) {
  const queryClient = useQueryClient()
  const queryKey = attachmentsQueryKey(resource, id, collection)

  const listQuery = useAttachmentList(resource, id, collection)

  const uploadMutation = useMutation<UploadBatchResult, AxiosError, File[]>({
    mutationFn: async (files) => {
      const failed: FailedUpload[] = []
      let uploaded = 0

      // Sequential on purpose: the endpoint takes one file per request, and a
      // burst of parallel multipart bodies is what trips server upload limits.
      for (const file of files) {
        try {
          await uploadAttachment({ resource, id, collection, file })
          uploaded += 1
        } catch (error) {
          failed.push({ name: file.name, error })
        }
      }

      return { uploaded, failed }
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey })
    },
  })

  const deleteMutation = useMutation<void, AxiosError, number>({
    mutationFn: (attachmentId) => deleteAttachment(attachmentId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey })
    },
  })

  return {
    documents: listQuery.data?.documents ?? EMPTY_DOCUMENTS,
    legacyUrl: listQuery.data?.legacyUrl ?? null,
    isLoading: listQuery.isLoading,
    /** When the list last arrived from the server (0 = not yet): drives `useMarkReadOnLoad`. */
    loadedAt: listQuery.dataUpdatedAt,
    isError: listQuery.isError,
    refetch: listQuery.refetch,
    upload: uploadMutation.mutateAsync,
    isUploading: uploadMutation.isPending,
    remove: deleteMutation.mutateAsync,
  }
}
