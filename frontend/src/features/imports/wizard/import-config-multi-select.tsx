import type { ReactElement } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import type { ForSelectItem } from '@/features/for-select/types'

interface ImportConfigMultiSelectProps {
  /** Resource segment of the for-select endpoint, e.g. `products` -> `/products/for-select`. */
  resource: string
  value: number[]
  onChange: (next: number[]) => void
  /** Resolved label of the global field, used as the picker's accessible trigger name. */
  triggerLabel: string
  /** Extra query params (spec 0094: `{ category_ids }` scoping `product_ids` by the chosen campaign). */
  params?: Record<string, number[]>
  disabled?: boolean
  /** Already-known items for the selected ids (edit-mode hydration), mirrors `ImportConfigRelationSelect`. */
  selectedItems?: ForSelectItem[]
  /** Forwarded by `FormControl` for the accessible-error triad (frontend.md §10). */
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/**
 * The `multiple: true` branch of a global-configuration field (spec 0094
 * AC-050) — mirrors `ImportConfigRelationSelect`'s single-value branch but
 * on `AsyncPaginatedMultiSelect`. No quick-create affordance: spec 0028's "+"
 * is reserved for the campaign/source single pickers.
 */
export function ImportConfigMultiSelect({
  resource,
  value,
  onChange,
  triggerLabel,
  params,
  disabled,
  selectedItems,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: ImportConfigMultiSelectProps): ReactElement {
  const { t } = useTranslation('importWizard')

  return (
    <AsyncPaginatedMultiSelect
      resource={resource}
      value={value}
      onChange={onChange}
      selectedItems={selectedItems}
      params={params}
      disabled={disabled}
      id={id}
      aria-describedby={ariaDescribedBy}
      aria-invalid={ariaInvalid}
      labels={{
        placeholder: t('config.multiSelect.placeholder'),
        searchPlaceholder: t('config.multiSelect.searchPlaceholder'),
        empty: t('config.multiSelect.empty'),
        error: t('config.multiSelect.error'),
        removeLabel: t('config.multiSelect.remove'),
        triggerLabel,
        retry: t('config.multiSelect.retry'),
      }}
    />
  )
}
