import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateEmailTemplatePayload,
  EmailTemplate,
  EmailTemplateWithPermissions,
  UpdateEmailTemplatePayload,
} from '@/features/email-templates/types'

/**
 * Fetches a single email template detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchEmailTemplate(id: number): Promise<EmailTemplateWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<EmailTemplate, ResourcePermissions>>(
    `/email-templates/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Creates an email template. Returns the created resource from the envelope `data`. */
export async function createEmailTemplate(payload: CreateEmailTemplatePayload): Promise<EmailTemplate> {
  const { data } = await apiClient.post<ApiResponse<EmailTemplate>>('/email-templates', payload)
  return data.data
}

/** Partially updates an email template (PATCH). `module` is never a key of the payload (data_contract). */
export async function updateEmailTemplate(
  id: number,
  payload: UpdateEmailTemplatePayload,
): Promise<EmailTemplate> {
  const { data } = await apiClient.patch<ApiResponse<EmailTemplate>>(`/email-templates/${id}`, payload)
  return data.data
}

/** Deletes an email template. Backend responds 204 with no body. */
export async function deleteEmailTemplate(id: number): Promise<void> {
  await apiClient.delete(`/email-templates/${id}`)
}
