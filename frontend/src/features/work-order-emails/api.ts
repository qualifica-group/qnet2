import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  ComposeContext,
  ImportAttachmentsPayload,
  OutboundEmail,
  OutboundEmailListItem,
  OutboundEmailListMeta,
  OutboundEmailPayload,
  RenderTemplateResult,
} from '@/features/work-order-emails/types'

/** Query key root shared by every cache entry this feature owns. */
const ROOT = 'work-order-emails'

export function workOrderEmailsListQueryKey(workOrderId: number) {
  return [ROOT, workOrderId, 'list'] as const
}

export function workOrderEmailQueryKey(workOrderId: number, emailId: number) {
  return [ROOT, workOrderId, 'detail', emailId] as const
}

export function workOrderEmailComposeContextQueryKey(workOrderId: number) {
  return [ROOT, workOrderId, 'compose-context'] as const
}

/** `data`/`meta` are top-level siblings on this envelope, not `meta` nested under `data` (frozen contract). */
interface ListEnvelope extends ApiResponse<OutboundEmailListItem[]> {
  meta: OutboundEmailListMeta
}

export interface WorkOrderEmailsPage {
  data: OutboundEmailListItem[]
  meta: OutboundEmailListMeta
}

/** One Laravel-paginated page of the commessa's email history (D-2/D-3: own drafts + everyone's queued/sent/failed). */
export async function listWorkOrderEmails(workOrderId: number, page: number): Promise<WorkOrderEmailsPage> {
  const { data } = await apiClient.get<ListEnvelope>(`/work-orders/${workOrderId}/emails`, { params: { page } })
  return { data: data.data, meta: data.meta }
}

/** "Nuova email": creates an empty draft, request body optional (D-2). */
export async function createWorkOrderEmailDraft(workOrderId: number): Promise<OutboundEmail> {
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(`/work-orders/${workOrderId}/emails`, {})
  return data.data
}

export async function fetchWorkOrderEmail(workOrderId: number, emailId: number): Promise<OutboundEmail> {
  const { data } = await apiClient.get<ApiResponse<OutboundEmail>>(`/work-orders/${workOrderId}/emails/${emailId}`)
  return data.data
}

export async function updateWorkOrderEmail(
  workOrderId: number,
  emailId: number,
  payload: OutboundEmailPayload,
): Promise<OutboundEmail> {
  const { data } = await apiClient.patch<ApiResponse<OutboundEmail>>(
    `/work-orders/${workOrderId}/emails/${emailId}`,
    payload,
  )
  return data.data
}

export async function deleteWorkOrderEmail(workOrderId: number, emailId: number): Promise<void> {
  await apiClient.delete(`/work-orders/${workOrderId}/emails/${emailId}`)
}

/** Draft or `failed` -> `queued` (D-2/D-14): the caller PATCHes first, this only flips the state. */
export async function sendWorkOrderEmail(workOrderId: number, emailId: number): Promise<OutboundEmail> {
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(
    `/work-orders/${workOrderId}/emails/${emailId}/send`,
    {},
  )
  return data.data
}

export async function fetchWorkOrderEmailComposeContext(workOrderId: number): Promise<ComposeContext> {
  const { data } = await apiClient.get<ApiResponse<ComposeContext>>(
    `/work-orders/${workOrderId}/emails/compose-context`,
  )
  return data.data
}

export async function renderWorkOrderEmailTemplate(
  workOrderId: number,
  emailTemplateId: number,
): Promise<RenderTemplateResult> {
  const { data } = await apiClient.post<ApiResponse<RenderTemplateResult>>(
    `/work-orders/${workOrderId}/emails/render-template`,
    { email_template_id: emailTemplateId },
  )
  return data.data
}

/**
 * Uploads a file attachment (D-7a). Multipart body: axios infers the
 * `multipart/form-data` boundary from the `FormData` instance, so no
 * `Content-Type` is forced here (never force one — it would strip the
 * boundary and break the upload).
 */
export async function uploadWorkOrderEmailAttachment(
  workOrderId: number,
  emailId: number,
  file: File,
): Promise<OutboundEmail> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(
    `/work-orders/${workOrderId}/emails/${emailId}/attachments`,
    formData,
  )
  return data.data
}

/** Copies attachments from documents / a document bundle / the quote PDF onto the email (D-7b/c/d). */
export async function importWorkOrderEmailAttachments(
  workOrderId: number,
  emailId: number,
  payload: ImportAttachmentsPayload,
): Promise<OutboundEmail> {
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(
    `/work-orders/${workOrderId}/emails/${emailId}/attachments/import`,
    payload,
  )
  return data.data
}

export async function removeWorkOrderEmailAttachment(
  workOrderId: number,
  emailId: number,
  attachmentId: number,
): Promise<OutboundEmail> {
  const { data } = await apiClient.delete<ApiResponse<OutboundEmail>>(
    `/work-orders/${workOrderId}/emails/${emailId}/attachments/${attachmentId}`,
  )
  return data.data
}

/**
 * Streams an email attachment's binary (D-8: `email_attachments` is closed to
 * the generic `/api/attachments*` endpoints — this nested, commessa-scoped
 * route is the only door). Never a plain anchor on a URL: the endpoint sits
 * behind `auth:sanctum`, mirrors `fetchAttachmentBinary`.
 */
export async function downloadWorkOrderEmailAttachment(
  workOrderId: number,
  emailId: number,
  attachmentId: number,
): Promise<Blob> {
  const { data } = await apiClient.get<Blob>(
    `/work-orders/${workOrderId}/emails/${emailId}/attachments/${attachmentId}/download`,
    { responseType: 'blob' },
  )
  return data
}
