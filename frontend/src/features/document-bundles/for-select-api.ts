import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type { ForSelectItem, ForSelectParams, PaginatedResponse } from '@/features/for-select/types'

/** Resource segment for the document-bundles for-select endpoint. */
export const DOCUMENT_BUNDLES_FOR_SELECT_RESOURCE = 'document-bundles'

/**
 * The presentation bag this resource projects alongside `{id, label}`, so the
 * composer's "Da modello documenti" picker can show the file count without a
 * second request.
 */
export interface DocumentBundleForSelectMeta {
  files_count: number
}

/** A single document bundle option as returned by `GET /api/document-bundles/for-select`. */
export interface DocumentBundleForSelectItem extends ForSelectItem {
  meta: DocumentBundleForSelectMeta
}

/**
 * Fetches a page of document bundle options from
 * `GET /api/document-bundles/for-select`. Reuses the generic fetcher and
 * narrows its meta-less `ForSelectItem` to the richer shape this endpoint
 * actually returns, same approach as `fetchTaskImportancesForSelect`. Only
 * active rows are returned unless explicitly requested via `ids` (edit-mode
 * hydration).
 */
export async function fetchDocumentBundlesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<DocumentBundleForSelectItem>> {
  const response = await fetchForSelect(DOCUMENT_BUNDLES_FOR_SELECT_RESOURCE, params)
  return { ...response, items: response.items as DocumentBundleForSelectItem[] }
}

interface UseDocumentBundlesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a document bundle single-select (composer's "Da
 * modello documenti"): debounced server search, offset pagination and
 * `ids[]` hydration, bound to the `document-bundles` resource.
 */
export function useDocumentBundlesForSelect({
  search,
  ids,
  enabled,
}: UseDocumentBundlesForSelectOptions) {
  return useForSelect({
    resource: DOCUMENT_BUNDLES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
