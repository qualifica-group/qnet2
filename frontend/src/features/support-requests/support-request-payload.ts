import type { SupportRequestSubmitValues } from '@/features/support-requests/support-request-schema'

/** Page and browser context the backend appends to the task description (spec 0334 D-6). */
export interface SupportRequestContext {
  page_url: string
  user_agent: string
}

/** Current page (path + query, no origin) and browser, read at submit time. */
export function readSupportRequestContext(): SupportRequestContext {
  return {
    page_url: `${window.location.pathname}${window.location.search}`,
    user_agent: navigator.userAgent,
  }
}

/** Builds the multipart body: booleans as "1"/"0", one `attachments[]` entry per file. */
export function buildSupportRequestFormData(
  values: SupportRequestSubmitValues,
  context: SupportRequestContext,
): FormData {
  const body = new FormData()
  body.append('support_topic_id', String(values.support_topic_id))
  body.append('description', values.description)
  body.append('is_urgent', values.is_urgent ? '1' : '0')
  for (const file of values.attachments) {
    body.append('attachments[]', file)
  }
  body.append('page_url', context.page_url)
  body.append('user_agent', context.user_agent)
  return body
}
