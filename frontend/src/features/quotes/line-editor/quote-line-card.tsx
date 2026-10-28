import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { ForSelectItem } from '@/features/for-select/types'
import { ProductSupplierSelect } from '@/features/products/product-supplier-select'
import type { ProductSupplierOption, SupplierAmount } from '@/features/products/product-suppliers'
import { SupplierCommissionNote } from '@/features/products/supplier-commission-note'
import { useSupplierCommissions } from '@/features/products/use-supplier-commissions'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { computeLineAmounts } from '@/features/quotes/quote-totals'
import type { QuoteLineVatRateRef } from '@/features/quotes/types'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import {
  LINE_CARD_CLASS,
  LINE_CARD_FOOTER_CLASS,
  LINE_CARD_STRIP_CLASS,
  QuoteLineAmounts,
  QuoteLineCardHeading,
} from './quote-line-card-parts'
import type { QuoteLineCardSlots, QuoteLineProductView } from './types'

/** `GET /vat-rates/for-select` meta: the decimal percentage of the picked rate (feeds the live preview). */
interface VatRateForSelectItem extends ForSelectItem {
  meta: { rate: string | null }
}

interface QuoteLineCardProps {
  /** 1-based position among the visible rows: the row's name in every label. */
  position: number
  row: QuoteLineFormValues
  /** `undefined` while its label is still loading, or when the row lost its product (its category was dropped). */
  product?: QuoteLineProductView
  categoryPath: string | null
  knownVatRate?: QuoteLineVatRateRef
  disabled: boolean
  /** Spec 0114: amounts only, the server congeals quantity/price/VAT. */
  simplified: boolean
  error?: QuoteLineRowErrors
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  onChangeField: (patch: Partial<QuoteLineFormValues>) => void
  /** Spec 0320: the product's suppliers; the supplier select shows only when there are any. */
  suppliers?: ProductSupplierOption[]
  onChangeSupplier?: (option: ProductSupplierOption) => void
  /** Which amount the supplier options show: the sale price on a revenue row, the cost on a cost row (rev.2). */
  supplierAmount?: SupplierAmount
  onRemove: () => void
  /** What the channel adds to the card (commissions, associated product, additional description). */
  slots?: QuoteLineCardSlots
}

/** One height for the row's three controls: the VAT trigger otherwise stands taller than the inputs. */
const INPUT_CLASS = 'h-8 text-sm'
const VAT_TRIGGER_CLASS = 'min-h-8 py-1'

/** Shown while a row's product label is still resolving: never the bare id. */
const PENDING_LABEL = '…'

const NO_SUPPLIERS: ProductSupplierOption[] = []

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

function parseNumberInput(text: string): number | null {
  return text === '' ? null : Number(text)
}

/**
 * One line on two levels (spec 0268): the product — what the operator reads
 * first — then the editable amounts and their live result; a channel's own
 * extras ride the `slots` (spec 0269). Same fields and accessible names as
 * the former Offerte row (`quotes.form.line*`).
 */
export function QuoteLineCard({
  position,
  row,
  product,
  categoryPath,
  knownVatRate,
  disabled,
  simplified,
  error,
  vatRatePercentFor,
  rememberVatRatePercent,
  onChangeField,
  suppliers = NO_SUPPLIERS,
  onChangeSupplier,
  supplierAmount = 'price',
  onRemove,
  slots,
}: QuoteLineCardProps) {
  const { t } = useTranslation()
  const rowId = useId()
  const vatPercent = row.vat_rate_id === null ? null : vatRatePercentFor(row.vat_rate_id)
  const amounts = computeLineAmounts(row.quantity ?? 0, row.unit_price ?? 0, vatPercent)
  const quantityErrorId = `${rowId}-quantity-error`
  const unitPriceErrorId = `${rowId}-unit-price-error`
  const productErrorId = `${rowId}-product-error`
  const supplierErrorId = `${rowId}-supplier-error`
  const commissionNoteId = `${rowId}-commission-note`
  const { commissionOf } = useSupplierCommissions({
    productId: row.product_id,
    categoryId: null,
    supplierIds: suppliers.map((supplier) => supplier.supplier_id),
  })
  const commission = commissionOf(row.supplier_id ?? null)
  // Rev.3 D-23: a cost row of a commissioned supplier is free of charge; a sale row only shows the note.
  const priceLocked = commission !== null && supplierAmount === 'cost'
  const vatRateItem: ForSelectItem | null = knownVatRate ? { id: knownVatRate.id, label: knownVatRate.name } : null

  return (
    <li className={LINE_CARD_CLASS}>
      <QuoteLineCardHeading
        position={position}
        name={product?.name ?? (row.product_id === null ? t('quotes.lineEditor.rows.productMissing') : PENDING_LABEL)}
        typology={product?.typology}
        code={product?.code}
        categoryPath={product ? categoryPath : null}
        note={
          error?.product_id ? (
            <span id={productErrorId} role="alert" className="text-[11px] text-destructive">
              {error.product_id.message}
            </span>
          ) : null
        }
      >
        {slots?.actions}
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={t('quotes.form.lineRemove', { n: position })}
          disabled={disabled}
          onClick={onRemove}
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </QuoteLineCardHeading>

      <div className={LINE_CARD_STRIP_CLASS}>
        {simplified ? null : (
          <div className="flex flex-wrap items-start gap-2">
            <div className="flex w-20 flex-col gap-1">
              <Input
                type="number"
                step="0.01"
                min={0}
                aria-label={t('quotes.form.lineQuantity', { n: position })}
                aria-invalid={!!error?.quantity}
                aria-describedby={error?.quantity ? quantityErrorId : undefined}
                disabled={disabled}
                value={numberInputValue(row.quantity)}
                onChange={(event) => onChangeField({ quantity: parseNumberInput(event.target.value) })}
                className={INPUT_CLASS}
              />
              {error?.quantity ? (
                <span id={quantityErrorId} role="alert" className="text-[11px] text-destructive">
                  {error.quantity.message}
                </span>
              ) : null}
            </div>
            <span className="pt-2 text-xs text-muted-foreground">{row.unit_of_measure?.symbol ?? ''} ×</span>
            <div className="flex w-28 flex-col gap-1">
              <Input
                type="number"
                step="0.01"
                min={0}
                aria-label={t('quotes.form.lineUnitPrice', { n: position })}
                aria-invalid={!!error?.unit_price}
                aria-describedby={error?.unit_price ? unitPriceErrorId : priceLocked ? commissionNoteId : undefined}
                disabled={disabled || priceLocked}
                value={numberInputValue(row.unit_price)}
                onChange={(event) => onChangeField({ unit_price: parseNumberInput(event.target.value) })}
                className={INPUT_CLASS}
              />
              {error?.unit_price ? (
                <span id={unitPriceErrorId} role="alert" className="text-[11px] text-destructive">
                  {error.unit_price.message}
                </span>
              ) : null}
            </div>
            <div className="w-36">
              <AsyncPaginatedSelect
                resource={VAT_RATES_FOR_SELECT_RESOURCE}
                value={row.vat_rate_id}
                onChange={(vatRateId) => onChangeField({ vat_rate_id: vatRateId })}
                onItemChange={(item) => {
                  const vatItem = item as VatRateForSelectItem | null
                  if (vatItem?.meta.rate != null) {
                    rememberVatRatePercent(vatItem.id, Number(vatItem.meta.rate))
                  }
                }}
                selectedItem={vatRateItem}
                disabled={disabled}
                className={VAT_TRIGGER_CLASS}
                labels={{
                  placeholder: t('quotes.form.lineVatRatePlaceholder'),
                  searchPlaceholder: t('quotes.form.lineVatRateSearch'),
                  empty: t('quotes.form.lineVatRateEmpty'),
                  error: t('quotes.form.lineVatRateLoadError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('quotes.form.lineVatRate', { n: position }),
                  retry: t('common.retry'),
                }}
              />
            </div>
          </div>
        )}

        {suppliers.length > 0 ? (
          <div className="flex w-full max-w-60 flex-col gap-1">
            <ProductSupplierSelect
              suppliers={suppliers}
              amount={supplierAmount}
              value={row.supplier_id ?? null}
              fallback={row.supplier}
              disabled={disabled}
              onChange={(_, option) => onChangeSupplier?.(option)}
              aria-invalid={!!error?.supplier_id}
              aria-describedby={error?.supplier_id ? supplierErrorId : undefined}
              labels={{
                placeholder: t('quotes.form.lineSupplierPlaceholder'),
                searchPlaceholder: t('quotes.form.lineSupplierSearch'),
                empty: t('quotes.form.lineSupplierEmpty'),
                triggerLabel: t('quotes.form.lineSupplier', { n: position }),
              }}
            />
            {error?.supplier_id ? (
              <span id={supplierErrorId} role="alert" className="text-[11px] text-destructive">
                {error.supplier_id.message}
              </span>
            ) : null}
            <SupplierCommissionNote commission={commission} id={commissionNoteId} />
          </div>
        ) : null}

        {slots?.extraField ? <div className="w-full max-w-60">{slots.extraField}</div> : null}

        <QuoteLineAmounts net={amounts.net} vat={amounts.vat} total={amounts.total} />
      </div>

      {slots?.footer ? <div className={LINE_CARD_FOOTER_CLASS}>{slots.footer}</div> : null}
    </li>
  )
}
