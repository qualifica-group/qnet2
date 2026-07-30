import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { DocumentLayoutModule } from '@/features/document-layouts/types'

/**
 * A single variable token exposed by `GET /document-layouts/variables` (spec
 * 0069 `variables` endpoint). The contract key is `variable` (never `token`,
 * see `layout-config.ts`'s naming note) — this is the reference actually
 * inserted into a `runs[].text` (`{category.key}`).
 */
export interface DocumentLayoutVariable {
  variable: string
  label: string
  type: 'string' | 'number' | 'date' | 'currency'
  example: string
}

export interface DocumentLayoutVariableCategory {
  key: string
  label: string
  variables: DocumentLayoutVariable[]
}

/** The per-module variable catalog, already field-permission-filtered server-side (D-6). */
export interface DocumentLayoutVariablesCatalog {
  module: DocumentLayoutModule
  categories: DocumentLayoutVariableCategory[]
}

/** Query key for the per-module variables catalog. */
export function documentLayoutVariablesKey(module: DocumentLayoutModule) {
  return ['document-layouts', 'variables', module] as const
}

/** Fetches the variable catalog for `module` from `GET /document-layouts/variables`. */
export async function fetchDocumentLayoutVariables(
  module: DocumentLayoutModule,
): Promise<DocumentLayoutVariablesCatalog> {
  const { data } = await apiClient.get<ApiResponse<DocumentLayoutVariablesCatalog>>(
    '/document-layouts/variables',
    { params: { module } },
  )
  return data.data
}

/** The catalog rarely changes within a session (custom fields/attributes aside); avoid refetch churn. */
const VARIABLES_STALE_TIME_MS = 5 * 60 * 1000

/**
 * Loads the per-module variable catalog backing the editor's searchable
 * variable picker (wave 2, out of this spec's UI scope — this hook is the
 * data layer the picker will consume). `enabled` lets a caller defer the
 * fetch until `module` is actually known (e.g. before a create form has
 * picked one).
 */
export function useDocumentLayoutVariables(module: DocumentLayoutModule, enabled = true) {
  return useQuery({
    queryKey: documentLayoutVariablesKey(module),
    queryFn: () => fetchDocumentLayoutVariables(module),
    enabled,
    staleTime: VARIABLES_STALE_TIME_MS,
  })
}
