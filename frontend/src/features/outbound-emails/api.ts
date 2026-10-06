import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  ComposeContext,
  CreateOutboundEmailPayload,
  EmailOwnerRef,
  ImportAttachmentsPayload,
  OutboundEmail,
  OutboundEmailListItem,
  OutboundEmailListMeta,
  OutboundEmailPayload,
  RenderTemplateResult,
} from '@/features/outbound-emails/types'

/** Query key root shared by every cache entry this feature owns. */
const ROOT = 'outbound-emails'

/** Owner-scoped base URL: `/work-orders/{id}/emails` or `/invoices/{id}/emails` (same contract, spec 0195 D-10). */
function emailsBase(owner: EmailOwnerRef): string {
  return `/${owner.type}/${owner.id}/emails`
}

export function outboundEmailsListQueryKey(owner: EmailOwnerRef) {
  return [ROOT, owner.type, owner.id, 'list'] as const
}

export function outboundEmailQueryKey(owner: EmailOwnerRef, emailId: number) {
  return [ROOT, owner.type, owner.id, 'detail', emailId] as const
}

export function outboundEmailComposeContextQueryKey(owner: EmailOwnerRef) {
  return [ROOT, owner.type, owner.id, 'compose-context'] as const
}

/** `data`/`meta` are top-level siblings on this envelope, not `meta` nested under `data` (frozen contract). */
interface ListEnvelope extends ApiResponse<OutboundEmailListItem[]> {
  meta: OutboundEmailListMeta
}

export interface OutboundEmailsPage {
  data: OutboundEmailListItem[]
  meta: OutboundEmailListMeta
}

/** One Laravel-paginated page of the commessa's email history (D-2/D-3: own drafts + everyone's queued/sent/failed). */
export async function listOutboundEmails(owner: EmailOwnerRef, page: number): Promise<OutboundEmailsPage> {
  const { data } = await apiClient.get<ListEnvelope>(`${emailsBase(owner)}`, { params: { page } })
  return { data: data.data, meta: data.meta }
}

/** "Nuova email": creates an empty draft; `attach_pdf` (invoices, default true server-side) is optional (D-2/D-12). */
export async function createOutboundEmailDraft(
  owner: EmailOwnerRef,
  payload: CreateOutboundEmailPayload = {},
): Promise<OutboundEmail> {
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(emailsBase(owner), payload)
  return data.data
}

/** Invoice reminder (D-13): creates a `purpose=reminder` draft with the PDF attached; 409 when not overdue. */
export async function createOutboundEmailReminder(
  owner: EmailOwnerRef,
  payload: { email_template_id?: number | null } = {},
): Promise<OutboundEmail> {
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(`${emailsBase(owner)}/reminder`, payload)
  return data.data
}

export async function fetchOutboundEmail(owner: EmailOwnerRef, emailId: number): Promise<OutboundEmail> {
  const { data } = await apiClient.get<ApiResponse<OutboundEmail>>(`${emailsBase(owner)}/${emailId}`)
  return data.data
}

export async function updateOutboundEmail(
  owner: EmailOwnerRef,
  emailId: number,
  payload: OutboundEmailPayload,
): Promise<OutboundEmail> {
  const { data } = await apiClient.patch<ApiResponse<OutboundEmail>>(
    `${emailsBase(owner)}/${emailId}`,
    payload,
  )
  return data.data
}

export async function deleteOutboundEmail(owner: EmailOwnerRef, emailId: number): Promise<void> {
  await apiClient.delete(`${emailsBase(owner)}/${emailId}`)
}

/** Draft or `failed` -> `queued` (D-2/D-14): the caller PATCHes first, this only flips the state. */
export async function sendOutboundEmail(owner: EmailOwnerRef, emailId: number): Promise<OutboundEmail> {
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(
    `${emailsBase(owner)}/${emailId}/send`,
    {},
  )
  return data.data
}

export async function fetchOutboundEmailComposeContext(owner: EmailOwnerRef): Promise<ComposeContext> {
  const { data } = await apiClient.get<ApiResponse<ComposeContext>>(
    `${emailsBase(owner)}/compose-context`,
  )
  return data.data
}

export async function renderOutboundEmailTemplate(
  owner: EmailOwnerRef,
  emailTemplateId: number,
): Promise<RenderTemplateResult> {
  const { data } = await apiClient.post<ApiResponse<RenderTemplateResult>>(
    `${emailsBase(owner)}/render-template`,
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
export async function uploadOutboundEmailAttachment(
  owner: EmailOwnerRef,
  emailId: number,
  file: File,
): Promise<OutboundEmail> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(
    `${emailsBase(owner)}/${emailId}/attachments`,
    formData,
  )
  return data.data
}

/** Copies attachments from documents / a document bundle / the quote PDF onto the email (D-7b/c/d). */
export async function importOutboundEmailAttachments(
  owner: EmailOwnerRef,
  emailId: number,
  payload: ImportAttachmentsPayload,
): Promise<OutboundEmail> {
  const { data } = await apiClient.post<ApiResponse<OutboundEmail>>(
    `${emailsBase(owner)}/${emailId}/attachments/import`,
    payload,
  )
  return data.data
}

export async function removeOutboundEmailAttachment(
  owner: EmailOwnerRef,
  emailId: number,
  attachmentId: number,
): Promise<OutboundEmail> {
  const { data } = await apiClient.delete<ApiResponse<OutboundEmail>>(
    `${emailsBase(owner)}/${emailId}/attachments/${attachmentId}`,
  )
  return data.data
}

/**
 * Streams an email attachment's binary (D-8: `email_attachments` is closed to
 * the generic `/api/attachments*` endpoints — this nested, commessa-scoped
 * route is the only door). Never a plain anchor on a URL: the endpoint sits
 * behind `auth:sanctum`, mirrors `fetchAttachmentBinary`.
 */
export async function downloadOutboundEmailAttachment(
  owner: EmailOwnerRef,
  emailId: number,
  attachmentId: number,
): Promise<Blob> {
  const { data } = await apiClient.get<Blob>(
    `${emailsBase(owner)}/${emailId}/attachments/${attachmentId}/download`,
    { responseType: 'blob' },
  )
  return data
}
