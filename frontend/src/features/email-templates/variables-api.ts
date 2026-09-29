import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { EmailTemplateModule } from '@/features/email-templates/types'

/**
 * A single placeholder token exposed by `GET /email-templates/variables`
 * (spec 0175 D-4/AC-003). The contract key is `variable` — the reference
 * actually inserted into `subject`/`body` (`{category.key}`), same shape as
 * `DocumentLayoutVariable` but this endpoint returns the category list flat
 * (no `{module, categories}` wrapper), so it gets its own type rather than
 * reusing `document-layouts`'s.
 */
export interface EmailTemplateVariable {
  variable: string
  label: string
  type: 'string' | 'number' | 'date' | 'currency'
  example: string
}

export interface EmailTemplateVariableCategory {
  key: string
  label: string
  variables: EmailTemplateVariable[]
}

/** Query key for the per-module placeholder catalog. */
export function emailTemplateVariablesKey(module: EmailTemplateModule) {
  return ['email-templates', 'variables', module] as const
}

/** Fetches the placeholder catalog for `module` from `GET /email-templates/variables`. */
export async function fetchEmailTemplateVariables(
  module: EmailTemplateModule,
): Promise<EmailTemplateVariableCategory[]> {
  const { data } = await apiClient.get<ApiResponse<EmailTemplateVariableCategory[]>>(
    '/email-templates/variables',
    { params: { module } },
  )
  return data.data
}

/** The catalog rarely changes within a session; avoid refetch churn while the form is open. */
const VARIABLES_STALE_TIME_MS = 5 * 60 * 1000

/**
 * Loads the per-module placeholder catalog backing the form's segnaposto
 * picker (AC-022). `enabled` lets a caller defer the fetch (e.g. while the
 * form is not yet mounted).
 */
export function useEmailTemplateVariables(module: EmailTemplateModule, enabled = true) {
  return useQuery({
    queryKey: emailTemplateVariablesKey(module),
    queryFn: () => fetchEmailTemplateVariables(module),
    enabled,
    staleTime: VARIABLES_STALE_TIME_MS,
  })
}
