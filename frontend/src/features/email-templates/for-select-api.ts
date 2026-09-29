import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type { ForSelectItem, ForSelectParams, PaginatedResponse } from '@/features/for-select/types'
import type { EmailTemplateModule } from '@/features/email-templates/types'

/** Resource segment for the email-templates for-select endpoint. */
export const EMAIL_TEMPLATES_FOR_SELECT_RESOURCE = 'email-templates'

/**
 * A single email template option as returned by
 * `GET /api/email-templates/for-select`. Minimal by design (ADR 0011):
 * `label` = `name`, no `subtitle`/`meta` — the composer only needs to name
 * the template, the render happens server-side via render-template.
 */
export type EmailTemplateForSelectItem = ForSelectItem

/**
 * Fetches a page of email template options scoped to a `module` (D-10: only
 * `work_orders` exists today, but the param is required server-side and kept
 * explicit here rather than folded into a default). Reuses the generic
 * fetcher, same approach as `fetchTaskImportancesForSelect`. Only active rows
 * are returned unless explicitly requested via `ids` (edit-mode hydration).
 */
export async function fetchEmailTemplatesForSelect(
  module: EmailTemplateModule,
  params: ForSelectParams = {},
): Promise<PaginatedResponse<EmailTemplateForSelectItem>> {
  return fetchForSelect(EMAIL_TEMPLATES_FOR_SELECT_RESOURCE, {
    ...params,
    params: { ...params.params, module },
  })
}

interface UseEmailTemplatesForSelectOptions {
  /** D-10: only `work_orders` exists today; required, not defaulted. */
  module: EmailTemplateModule
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding the composer's template picker: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `email-templates` resource and scoped to `module`.
 */
export function useEmailTemplatesForSelect({
  module,
  search,
  ids,
  enabled,
}: UseEmailTemplatesForSelectOptions) {
  return useForSelect({
    resource: EMAIL_TEMPLATES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
    params: { module },
  })
}
