import { useMemo } from 'react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { PRODUCT_CATEGORY_NODES_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { RECIPIENT_RESOURCE_BY_TYPE } from './commission-recipient-resources'
import type { CommissionConfigurationFormValues } from './commission-configuration-schema'
import type { CommissionConfigurationRefs, CommissionRelationRef } from './types'

const NO_IDS: number[] = []

/** Resolves one picked id to the `{id, name}` its closed row shows, through the for-select hydration. */
function useRelationRef(resource: string, id: number | null): CommissionRelationRef | null {
  const ids = useMemo(() => (id === null ? NO_IDS : [id]), [id])
  const labels = useForSelectLabels({ resource, ids })
  const label = id === null ? undefined : labels.get(id)?.label
  return id !== null && label !== undefined ? { id, name: label } : null
}

/** The create draft only holds ids: this gives its closed rows the names (and the pickers their `selected`). */
export function useCommissionConfigurationDraftRefs(
  form: UseFormReturn<CommissionConfigurationFormValues>,
): CommissionConfigurationRefs {
  const [categoryId, productId, recipientId, recipientType] = useWatch({
    control: form.control,
    name: ['product_category_id', 'product_id', 'recipient_id', 'recipient_type'],
  })

  return {
    category: useRelationRef(PRODUCT_CATEGORY_NODES_FOR_SELECT_RESOURCE, categoryId),
    product: useRelationRef(PRODUCTS_FOR_SELECT_RESOURCE, productId),
    recipient: useRelationRef(RECIPIENT_RESOURCE_BY_TYPE[recipientType], recipientId),
  }
}
