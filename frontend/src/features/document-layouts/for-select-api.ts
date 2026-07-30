import { apiClient } from '@/api/client'
import { FOR_SELECT_PAGE_SIZE } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type { ForSelectItem, ForSelectParams, PaginatedResponse } from '@/features/for-select/types'
import type { DocumentLayoutModule } from '@/features/document-layouts/types'

/** Resource segment for the document-layouts for-select endpoint (spec 0069). */
export const DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE = 'document-layouts'

/** The `meta` block carried by every `/document-layouts/for-select` item. */
export interface DocumentLayoutForSelectMeta {
  is_default: boolean
  code: string
}

/**
 * A single document layout option as returned by
 * `GET /api/document-layouts/for-select`, `label` = name, `subtitle` = code.
 * The predefined layout of the requested `module` sorts first server-side.
 */
export interface DocumentLayoutForSelectItem extends ForSelectItem {
  meta: DocumentLayoutForSelectMeta
}

/**
 * Fetches a page of document layout options scoped to `module` (required by
 * the endpoint, spec 0069 `for-select`). Own typed request rather than the
 * meta-less generic `fetchForSelect` (mirrors `fetchProjectsForSelect`):
 * consumers need `meta.is_default`/`meta.code` without a cast.
 */
export async function fetchDocumentLayoutsForSelect(
  module: DocumentLayoutModule,
  params: Omit<ForSelectParams, 'params'> = {},
): Promise<PaginatedResponse<DocumentLayoutForSelectItem>> {
  const { search, offset = 0, limit = FOR_SELECT_PAGE_SIZE, ids } = params
  const { data } = await apiClient.get<PaginatedResponse<DocumentLayoutForSelectItem>>(
    `/${DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE}/for-select`,
    {
      params: {
        module,
        offset,
        limit,
        ...(search ? { search } : {}),
        ...(ids && ids.length > 0 ? { ids } : {}),
      },
      paramsSerializer: { indexes: true },
    },
  )
  return data
}

interface UseDocumentLayoutsForSelectOptions {
  module: DocumentLayoutModule
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a document layout single-select scoped to `module`
 * (the future "Layout" picker on the Preventivo form, spec 0070): debounced
 * server search, offset pagination and `ids[]` hydration. No consumer wires
 * this in yet in this spec — kept ready for the first module that references
 * `quotes.layout_id`.
 */
export function useDocumentLayoutsForSelect({
  module,
  search,
  ids,
  enabled,
}: UseDocumentLayoutsForSelectOptions) {
  return useForSelect({
    resource: DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
    params: { module },
  })
}
