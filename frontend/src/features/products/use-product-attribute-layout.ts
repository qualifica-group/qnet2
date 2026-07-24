import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { LayoutBlob, LayoutFormMode } from '@/features/attributes/attribute-layout-types'

/** The product form/detail only ever resolves the PRODUCT-context layout (spec 0062). */
const PRODUCT_ATTRIBUTE_LAYOUT_CONTEXT = 'product'

interface AttributeLayoutQueryResponse {
  layout: LayoutBlob | null
}

async function fetchProductAttributeLayout(
  categoryId: number,
  formMode: LayoutFormMode,
): Promise<LayoutBlob | null> {
  const { data } = await apiClient.get<ApiResponse<AttributeLayoutQueryResponse>>(
    `/product-categories/${categoryId}/attribute-layouts`,
    { params: { context: PRODUCT_ATTRIBUTE_LAYOUT_CONTEXT, form_mode: formMode } },
  )
  return data.data.layout
}

/**
 * Loads the selected category's configured PRODUCT-context layout (spec 0062
 * `data_contract`), for the given form mode — same `product-categories.view`
 * authorization as `useEffectiveAttributes`, which the product form already
 * calls. Gated on a selected category id, keyed by (category, form_mode) so
 * switching either re-fetches (mirrors `useEffectiveAttributes`). The
 * endpoint's own `attributes` field is intentionally NOT read here:
 * `useEffectiveAttributes` (spec 0061) stays the single source for the
 * effective attribute catalogue, unaffected by this addition. A failed or
 * still-loading fetch resolves to `null`, so the form degrades to the flat
 * fallback rather than blocking data entry (AC-007).
 */
export function useProductAttributeLayout(categoryId: number | null, formMode: LayoutFormMode) {
  return useQuery({
    queryKey: ['product-categories', categoryId ?? 0, 'attribute-layouts', PRODUCT_ATTRIBUTE_LAYOUT_CONTEXT, formMode] as const,
    queryFn: () => fetchProductAttributeLayout(categoryId as number, formMode),
    enabled: categoryId !== null,
  })
}
