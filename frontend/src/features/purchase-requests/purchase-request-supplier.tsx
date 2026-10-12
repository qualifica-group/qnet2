import type { Control, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import type { NamedRef } from '@/features/purchase-requests/types'

/** Supplier picker filter: only registries flagged `is_supplier` (spec 0208 D-14, 0209 D-1). */
const SUPPLIER_PARAMS = { is_supplier: 1 } as const
/** The "+" quick-create opens the registry form with the supplier flag already on. */
const SUPPLIER_PRESETS = { is_supplier: true } as const

interface SupplierPickerProps {
  /** Currently selected supplier (carries the label, so no lookup is needed to show it). */
  selected: NamedRef | null
  /** Fired on pick, clear and quick-create. */
  onSelect: (supplier: NamedRef | null) => void
  label: string
  disabled?: boolean
  /** Shows the "+" quick-create next to the picker. */
  quickCreate?: boolean
  className?: string
}

/** Registry picker restricted to suppliers, shared by the RDA line, the status dialog and the assign dialog. */
export function SupplierPicker({ selected, onSelect, label, disabled = false, quickCreate = false, className }: SupplierPickerProps) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(REGISTRIES_FOR_SELECT_RESOURCE, SUPPLIER_PRESETS)
  const value = selected?.id ?? null
  return (
    <AsyncPaginatedSelect
      resource={REGISTRIES_FOR_SELECT_RESOURCE}
      params={SUPPLIER_PARAMS}
      value={value}
      onChange={() => undefined}
      onItemChange={(item) => onSelect(item ? { id: item.id, name: item.label } : null)}
      selectedItem={selected ? { id: selected.id, label: selected.name } : selectedItemFor(value)}
      disabled={disabled}
      className={className}
      labels={{
        placeholder: t('purchaseRequests.lines.supplierPlaceholder'),
        searchPlaceholder: t('purchaseRequests.select.search'),
        empty: t('purchaseRequests.select.empty'),
        error: t('purchaseRequests.select.error'),
        clearLabel: t('common.clear'),
        triggerLabel: label,
        retry: t('common.retry'),
      }}
      action={quickCreate ? renderAction((ref) => onSelect({ id: ref.id, name: ref.name }), disabled) : undefined}
    />
  )
}

interface LineSupplierSelectProps {
  control: Control<PurchaseRequestFormValues>
  setValue: UseFormSetValue<PurchaseRequestFormValues>
  index: number
  selected: NamedRef | null
  disabled: boolean
  label: string
}

/** Supplier of one RDA line, optional until the line is ordered (spec 0209 D-1/D-2). */
export function LineSupplierSelect({ control, setValue, index, selected, disabled, label }: LineSupplierSelectProps) {
  return (
    <FormField
      control={control}
      name={`lines.${index}.supplier_id`}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormControl>
            <SupplierPicker
              selected={selected}
              disabled={disabled}
              label={label}
              quickCreate
              onSelect={(supplier) => {
                field.onChange(supplier?.id ?? null)
                setValue(`lines.${index}.supplier_ref`, supplier, { shouldDirty: true })
              }}
            />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}
