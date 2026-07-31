import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { Attachment } from '@/features/attachments/types'

/**
 * TanStack Query key for an attachable owner's document list, scoped by
 * collection so different collections on the same owner never collide.
 */
export function attachmentsQueryKey(resource: string, id: number, collection: string) {
  return ['attachments', resource, id, collection] as const
}

/**
 * Lists the attachments of a polymorphic owner (`resource` is the
 * `attachable_type` alias, e.g. `'opportunity'`), optionally narrowed to a
 * named collection.
 */
export async function listAttachments(
  resource: string,
  id: number,
  collection: string,
): Promise<Attachment[]> {
  const { data } = await apiClient.get<ApiResponse<Attachment[]>>('/attachments', {
    params: { attachable_type: resource, attachable_id: id, collection },
  })
  return data.data
}

export interface UploadAttachmentPayload {
  resource: string
  id: number
  collection: string
  file: File
}

/**
 * Uploads a file to a polymorphic owner's collection.
 *
 * Multipart body: axios infers the `multipart/form-data` boundary from the
 * `FormData` instance, so no `Content-Type` is set here (never force one).
 */
export async function uploadAttachment({
  resource,
  id,
  collection,
  file,
}: UploadAttachmentPayload): Promise<Attachment> {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('collection', collection)
  formData.append('attachable_type', resource)
  formData.append('attachable_id', String(id))

  const { data } = await apiClient.post<ApiResponse<Attachment>>('/attachments', formData)
  return data.data
}

/** The two binary endpoints of an attachment: inline preview vs. forced save-as. */
export type AttachmentBinaryMode = 'view' | 'download'

/** TanStack Query key for one attachment's inline binary (thumbnail/preview). */
export function attachmentBinaryQueryKey(id: number) {
  return ['attachment-binary', id] as const
}

/**
 * Streams an attachment's binary through the authenticated axios client.
 *
 * The API authenticates with a Bearer token (localStorage), and the browser
 * never attaches it to a plain `<img src>` / `<a href>` / address-bar
 * navigation: the `view_url`/`download_url` of the resource cannot be handed
 * to the DOM as-is (they answer 401). The bytes are fetched here and reach the
 * DOM as a `blob:` URL instead.
 *
 * Serving a `blob:` URL in a tab runs it under the SPA origin, which would be
 * an XSS vector for active content — harmless here because the server-side
 * allowlist (`config/attachments.php`) accepts no SVG and no HTML.
 */
export async function fetchAttachmentBinary(
  id: number,
  mode: AttachmentBinaryMode,
): Promise<Blob> {
  const { data } = await apiClient.get<Blob>(`/attachments/${id}/${mode}`, {
    responseType: 'blob',
  })
  return data
}

/** Deletes an attachment (metadata + binary) by id. */
export async function deleteAttachment(id: number): Promise<void> {
  await apiClient.delete(`/attachments/${id}`)
}
