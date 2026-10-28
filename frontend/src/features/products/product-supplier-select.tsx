import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { ProductSupplierOption, SupplierAmount } from '@/features/products/product-suppliers'

/** Texts the caller supplies from its own namespace: the select owns no i18n keys. */
export interface ProductSupplierSelectLabels {
  placeholder: string
  searchPlaceholder: string
  empty: string
  /** Accessible name of the trigger (a repeated row has no visible label). */
  triggerLabel: string
}

interface ProductSupplierSelectProps {
  /** The product's suppliers, as listed by the for-select `meta.suppliers`. */
  suppliers: ProductSupplierOption[]
  /** The chosen supplier id, or null while nothing is chosen. */
  value: number | null
  /** Fired on pick with the id and the whole option (its `price`/`cost` feeds the row). */
  onChange: (supplierId: number, option: ProductSupplierOption) => void
  /**
   * A saved supplier the product no longer lists (spec 0320 D-5: a historical
   * row stays valid): shown as an inert extra option so the trigger keeps its name.
   */
  fallback?: { id: number; name: string } | null
  /** Which amount rides each option: the sale price (offer/revenue rows) or the purchase cost (costs, purchases). */
  amount: SupplierAmount
  labels: ProductSupplierSelectLabels
  disabled?: boolean
  className?: string
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/** "Name · amount": the amount is what the pick will precompile, so it rides the option. */
function optionLabel(option: ProductSupplierOption, amount: SupplierAmount): string {
  const formatted = formatEuro(option[amount])

  return formatted === '' ? option.name : `${option.name} · ${formatted}`
}

/**
 * Compact supplier picker of a product (spec 0320), shared by every row that
 * proposes a price or cost from the product's suppliers (offer lines, costs, purchases).
 * Presentational: the caller decides when it is shown (the product has
 * suppliers) and what a pick rewrites.
 */
export function ProductSupplierSelect({
  suppliers,
  value,
  onChange,
  fallback = null,
  amount,
  labels,
  disabled = false,
  className,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: ProductSupplierSelectProps) {
  const { t } = useTranslation()
  const options = useMemo(() => {
    const listed = suppliers.map((supplier) => ({ id: supplier.supplier_id, name: optionLabel(supplier, amount) }))
    const orphan = fallback && !suppliers.some((supplier) => supplier.supplier_id === fallback.id)

    return orphan ? [...listed, { id: fallback.id, name: fallback.name, disabled: true }] : listed
  }, [suppliers, fallback, amount])

  const handleChange = (supplierId: number) => {
    const option = suppliers.find((supplier) => supplier.supplier_id === supplierId)
    if (option) {
      onChange(supplierId, option)
    }
  }

  return (
    <SearchableSelect
      value={value}
      onChange={handleChange}
      options={options}
      disabled={disabled}
      className={className}
      id={id}
      aria-describedby={ariaDescribedBy}
      aria-invalid={ariaInvalid}
      labels={{
        placeholder: labels.placeholder,
        searchPlaceholder: labels.searchPlaceholder,
        empty: labels.empty,
        noMatch: labels.empty,
        error: labels.empty,
        retry: t('common.retry'),
        triggerLabel: labels.triggerLabel,
      }}
    />
  )
}
