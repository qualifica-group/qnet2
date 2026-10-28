import { useWatch, type Control, type DeepPartial } from 'react-hook-form'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import { PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE } from '@/features/product-typologies/for-select-api'
import { UNITS_OF_MEASURE_FOR_SELECT_RESOURCE } from '@/features/units-of-measure/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { DEFAULT_PRODUCT_USAGES } from '@/features/products/product-form-payload'
import type { ProductRecordSupplier, ProductRecordValues } from '@/features/products/product-record'
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

/** The draft rows whose supplier is picked; the row carries its own name (no label lookup). */
function draftSuppliers(rows: DeepPartial<ProductFormValues['suppliers']> | undefined): ProductRecordSupplier[] {
  return (rows ?? []).flatMap((row) =>
    row?.supplier_id != null
      ? [{ supplier_id: row.supplier_id, name: row.name ?? '', price: row.price ?? null, cost: row.cost ?? null, is_default: row.is_default ?? false }]
      : [],
  )
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
  const costTypes = useForSelectLabels({ resource: COST_TYPES_FOR_SELECT_RESOURCE, ids: idsOf(draft.cost_type_id) })
  const category = categories.options.find((option) => option.id === draft.category_id)

  const costTypeId = draft.cost_type_id
  const costTypeItem = costTypeId != null ? costTypes.get(costTypeId) : undefined
  const costType =
    costTypeId != null
      ? {
          id: costTypeId,
          name: costTypeItem?.label ?? PENDING_LABEL,
          color: (costTypeItem as { meta?: { color?: string } } | undefined)?.meta?.color ?? '',
        }
      : null

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
    cost_type: costType,
    usages: (draft.usages as ProductRecordValues['usages'] | undefined) ?? DEFAULT_PRODUCT_USAGES,
    vat_rate: refOf(vatRates, draft.vat_rate_id),
    suppliers: draftSuppliers(draft.suppliers),
    custom_fields: (draft.custom_fields ?? {}) as ProductRecordValues['custom_fields'],
    attribute_values: (draft.attribute_values ?? {}) as ProductRecordValues['attribute_values'],
  }
}
