import { useTranslation } from 'react-i18next'
import type { FieldError } from 'react-hook-form'
import { Lock, Trash2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { RadioGroupItem } from '@/components/ui/radio-group'
import type { ForSelectItem } from '@/features/for-select/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { computeProductMargin } from '@/features/products/product-margin'
import type { ProductSupplierRow } from '@/features/products/product-supplier-rows'
import type { SupplierCommission } from '@/features/products/product-suppliers'
import { SUPPLIER_EDITOR_ROW_GRID } from '@/features/products/product-suppliers-layout'
import { SupplierMarginValue, SupplierMobileLabel } from '@/features/products/product-suppliers-table'
import { SupplierCommissionNote } from '@/features/products/supplier-commission-note'

/** Filters the picker's `registries` for-select to `is_supplier` records only. */
const SUPPLIER_PARAMS: Record<string, string | number> = { is_supplier: 1 }
/** The "+" quick-create opens the registry form with the supplier flag already on. */
const SUPPLIER_PRESETS = { is_supplier: true } as const

export type SupplierRowErrors = { supplier_id?: FieldError; price?: FieldError; cost?: FieldError }
type AmountField = 'price' | 'cost'

function RowError({ id, error }: { id: string; error?: FieldError }) {
  return error?.message ? (
    <span id={id} role="alert" className="text-[11px] text-destructive">
      {error.message}
    </span>
  ) : null
}

interface AmountInputProps {
  field: AmountField
  n: number
  value: number | null
  disabled: boolean
  locked?: boolean
  error?: FieldError
  errorId: string
  /** The commission note that explains why the box is locked (rev.3 D-22). */
  noteId?: string
  onChange: (value: number | null) => void
}

/** One money input of a supplier row: "€" prefix, right-aligned digits; required, so an emptied box is an error, not 0. */
function AmountInput({ field, n, value, disabled, locked = false, error, errorId, noteId, onChange }: AmountInputProps) {
  const { t } = useTranslation()

  return (
    <div className="flex min-w-0 flex-col gap-1">
      <SupplierMobileLabel variant="editor">{t(`products.form.suppliers.${field}`)}</SupplierMobileLabel>
      <div className="relative">
        <span className="pointer-events-none absolute top-1/2 left-2 -translate-y-1/2 text-xs text-muted-foreground" aria-hidden="true">
          €
        </span>
        <Input
          type="number"
          step="0.01"
          min="0"
          inputMode="decimal"
          aria-label={t(`products.form.suppliers.${field}N`, { n })}
          disabled={disabled || locked}
          value={value === null ? '' : String(value)}
          onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
          className={cn('h-8 pl-5 text-right text-sm tabular-nums', locked && 'pr-6')}
          aria-invalid={!!error}
          aria-describedby={error ? errorId : noteId}
        />
        {locked ? (
          <Lock className="pointer-events-none absolute top-1/2 right-2 size-3 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        ) : null}
      </div>
      <RowError id={errorId} error={error} />
    </div>
  )
}

interface SupplierEditorRowProps {
  n: number
  row: ProductSupplierRow
  takenIds: ReadonlySet<number>
  commission: SupplierCommission | null
  disabled: boolean
  error?: SupplierRowErrors
  rowId: string
  onPatch: (patch: Partial<ProductSupplierRow>) => void
  onRemove: () => void
}

/**
 * One editable supplier (spec 0320): registry picker, price, cost, live margin, the default radio and remove —
 * on the editor's column grid from the `@3xl` container up, stacked with visible labels below. A supplier paid
 * through a commission has its cost locked at 0 with the commission pill under the row (rev.3 D-22).
 */
export function SupplierEditorRow({ n, row, takenIds, commission, disabled, error, rowId, onPatch, onRemove }: SupplierEditorRowProps) {
  const { t } = useTranslation()
  const create = useQuickCreateAction(REGISTRIES_FOR_SELECT_RESOURCE, SUPPLIER_PRESETS)
  const supplierErrorId = `${rowId}-supplier-error`
  const commissionNoteId = `${rowId}-commission-note`
  const selected: ForSelectItem | null =
    row.supplier_id !== null && row.name ? { id: row.supplier_id, label: row.name } : null
  const margin = commission ? null : computeProductMargin(row.cost, row.price)

  return (
    <div className={cn('flex flex-col gap-2 px-3 py-2', row.is_default && 'bg-muted/30')}>
      <div className={SUPPLIER_EDITOR_ROW_GRID}>
        <div className="flex min-w-0 flex-col gap-1">
          <SupplierMobileLabel variant="editor">{t('products.form.suppliers.supplier')}</SupplierMobileLabel>
          <AsyncPaginatedSelect
            resource={REGISTRIES_FOR_SELECT_RESOURCE}
            params={SUPPLIER_PARAMS}
            value={row.supplier_id}
            onChange={() => {}}
            onItemChange={(item) => onPatch({ supplier_id: item?.id ?? null, name: item?.label ?? '' })}
            selectedItem={selected}
            isItemDisabled={(item) => takenIds.has(item.id) && item.id !== row.supplier_id}
            action={create.renderAction((ref) => onPatch({ supplier_id: ref.id, name: ref.name }), disabled)}
            disabled={disabled}
            className="min-h-8 py-1"
            aria-invalid={!!error?.supplier_id}
            aria-describedby={error?.supplier_id ? supplierErrorId : undefined}
            labels={{
              placeholder: t('products.form.suppliers.placeholder'),
              searchPlaceholder: t('products.form.suppliers.search'),
              empty: t('products.form.suppliers.empty'),
              error: t('products.form.suppliers.loadError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('products.form.suppliers.supplierN', { n }),
              retry: t('common.retry'),
            }}
          />
          <RowError id={supplierErrorId} error={error?.supplier_id} />
        </div>
        <div className="grid grid-cols-3 gap-2 @3xl:contents">
          <AmountInput field="price" n={n} value={row.price} disabled={disabled} error={error?.price} errorId={`${rowId}-price-error`} onChange={(price) => onPatch({ price })} />
          <AmountInput
            field="cost"
            n={n}
            value={row.cost}
            disabled={disabled}
            locked={commission !== null}
            error={error?.cost}
            errorId={`${rowId}-cost-error`}
            noteId={commission ? commissionNoteId : undefined}
            onChange={(cost) => onPatch({ cost })}
          />
          <div className="flex min-w-0 flex-col gap-1 @3xl:h-8 @3xl:justify-center @3xl:text-right">
            <SupplierMobileLabel variant="editor">{t('products.form.suppliers.margin')}</SupplierMobileLabel>
            <SupplierMarginValue margin={margin} className="text-sm" />
          </div>
        </div>
        <div className="flex items-center justify-between gap-2 @3xl:contents">
          <label className="flex h-8 items-center gap-1.5 text-xs @3xl:justify-center">
            <RadioGroupItem value={String(n - 1)} disabled={disabled} aria-label={t('products.form.suppliers.defaultN', { n })} />
            <span className="@3xl:sr-only">{t('products.form.suppliers.default')}</span>
          </label>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
            aria-label={t('products.form.suppliers.removeN', { n })}
            disabled={disabled}
            onClick={onRemove}
          >
            <Trash2 className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
      </div>
      <SupplierCommissionNote commission={commission} id={commissionNoteId} />
    </div>
  )
}
