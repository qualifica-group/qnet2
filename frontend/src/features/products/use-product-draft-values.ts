import { useWatch, type Control } from 'react-hook-form'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE } from '@/features/product-typologies/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { UNITS_OF_MEASURE_FOR_SELECT_RESOURCE } from '@/features/units-of-measure/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { DEFAULT_PRODUCT_USAGES } from '@/features/products/product-form-payload'
import type { ProductRecordValues } from '@/features/products/product-record'
import type { ProductCategoryPicker } from '@/features/products/use-product-category-picker'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty set: a fresh `[]` per render would break the label hooks' memo. */
const NO_IDS: number[] = []

function idsOf(id: number | null | undefined): number[] {
  return id != null ? [id] : NO_IDS
}

function refOf(labels: Map<number, ForSelectItem>, id: number | null | undefined) {
  return id != null ? { id, name: labels.get(id)?.label ?? PENDING_LABEL } : null
}

/**
 * The create draft as the record's closed rows read it (spec 0218): the form
 * holds ids only, so every relation is named through `useForSelectLabels` —
 * the very cache the pickers fill for their own trigger — and the category
 * through the picker's own option list.
 */
export function useProductDraftValues(
  control: Control<ProductFormValues>,
  categories: ProductCategoryPicker,
): ProductRecordValues {
  const draft = useWatch({ control })
  const typologies = useForSelectLabels({
    resource: PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE,
    ids: idsOf(draft.product_typology_id),
  })
  const units = useForSelectLabels({ resource: UNITS_OF_MEASURE_FOR_SELECT_RESOURCE, ids: idsOf(draft.unit_of_measure_id) })
  const vatRates = useForSelectLabels({ resource: VAT_RATES_FOR_SELECT_RESOURCE, ids: idsOf(draft.vat_rate_id) })
  const suppliers = useForSelectLabels({ resource: REGISTRIES_FOR_SELECT_RESOURCE, ids: idsOf(draft.supplier_id) })
  const category = categories.options.find((option) => option.id === draft.category_id)

  return {
    code: draft.code ?? '',
    name: draft.name ?? '',
    description: draft.description ?? null,
    cost: draft.cost ?? null,
    price: draft.price ?? null,
    category: category ? { id: category.id, name: category.name } : null,
    business_function: null,
    product_typology: refOf(typologies, draft.product_typology_id),
    unit_of_measure: refOf(units, draft.unit_of_measure_id),
    product_type: draft.product_type ?? 'SERVICE',
    usages: (draft.usages as ProductRecordValues['usages'] | undefined) ?? DEFAULT_PRODUCT_USAGES,
    vat_rate: refOf(vatRates, draft.vat_rate_id),
    supplier: refOf(suppliers, draft.supplier_id),
    custom_fields: (draft.custom_fields ?? {}) as ProductRecordValues['custom_fields'],
    attribute_values: (draft.attribute_values ?? {}) as ProductRecordValues['attribute_values'],
  }
}
