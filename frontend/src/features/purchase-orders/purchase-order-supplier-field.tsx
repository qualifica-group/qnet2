import { useState } from 'react'
import type { Control, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { OrderRelationField } from '@/features/purchase-orders/purchase-order-fields'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'

/** Supplier picker filter and quick-create preset: only registries flagged `is_supplier` (spec 0210 D-14). */
const SUPPLIER_PARAMS = { is_supplier: 1 } as const
const SUPPLIER_PRESETS = { is_supplier: true } as const

/** `registries/for-select` exposes `meta.is_qualified_supplier` (spec 0210 D-15); anything but an explicit false counts as qualified. */
function isUnqualified(item: ForSelectItem | null): boolean {
  const meta = (item as { meta?: { is_qualified_supplier?: unknown } } | null)?.meta
  return meta?.is_qualified_supplier === false
}

interface OrderSupplierFieldProps {
  control: Control<PurchaseOrderFormValues>
  setValue: UseFormSetValue<PurchaseOrderFormValues>
  selected: RelationFieldRef | null
  /** Current supplier id and whether it is qualified: drives the red notice under the field. */
  supplierId: number | null
  qualified: boolean
}

/**
 * Supplier of the ODA (suppliers only, "+" quick-create). Picking an
 * unqualified supplier opens a modal: Abandon clears the field, "Proceed
 * anyway" keeps it; the red notice stays under the field while it is unqualified
 * (D-15, a UI warning only: the server never blocks). The modal opens on a
 * user pick only, never when an existing ODA is opened.
 */
export function OrderSupplierField({ control, setValue, selected, supplierId, qualified }: OrderSupplierFieldProps) {
  const { t } = useTranslation()
  const [pending, setPending] = useState<ForSelectItem | null>(null)

  const handleItemChange = (item: ForSelectItem | null) => {
    if (isUnqualified(item)) {
      setValue('supplier_qualified', false)
      setPending(item)
    }
  }

  const abandon = () => {
    setValue('supplier_id', null, { shouldDirty: true, shouldValidate: true })
    setValue('supplier_qualified', true)
    setPending(null)
  }

  return (
    <div className="grid content-start gap-1">
      <OrderRelationField
        control={control}
        name="supplier_id"
        resource={REGISTRIES_FOR_SELECT_RESOURCE}
        selected={selected}
        params={SUPPLIER_PARAMS}
        quickCreatePresets={SUPPLIER_PRESETS}
        required
        onValueChange={() => setValue('supplier_qualified', true)}
        onItemChange={handleItemChange}
      />
      {supplierId !== null && !qualified ? (
        <p role="alert" className="text-xs text-destructive">
          {t('purchaseOrderForm.supplier.unqualifiedWarning')}
        </p>
      ) : null}
      <AlertDialog open={pending !== null}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('purchaseOrderForm.supplier.dialogTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('purchaseOrderForm.supplier.dialogDescription', { name: pending?.label ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={abandon}>{t('purchaseOrderForm.supplier.abandon')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => setPending(null)}>{t('purchaseOrderForm.supplier.force')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
