import type { ReactNode } from 'react'
import type { Control, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Calculator, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { costTypeOptionRenderer } from '@/features/cost-types/cost-type-option-renderer'
import { COST_TYPES_FOR_SELECT_RESOURCE } from '@/features/cost-types/for-select-api'
import {
  LineDescriptionField,
  LineNumberField,
  LineProductSelect,
  LineRelationSelect,
} from '@/features/purchase-orders/purchase-order-line-inputs'
import { CAPTIONED_CELL, LINE_GRID, WIDE_CELL } from '@/features/purchase-orders/purchase-order-line-layout'
import type {
  PurchaseOrderFormValues,
  PurchaseOrderLineFormValues,
} from '@/features/purchase-orders/purchase-order-schema'
import { useOrderLineProductDefaults } from '@/features/purchase-orders/use-order-line-product-defaults'
import { extractVat, MissingVatRateError, previewLineAmounts } from '@/features/purchase-requests/purchase-request-amounts'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { UNITS_OF_MEASURE_FOR_SELECT_RESOURCE } from '@/features/units-of-measure/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { WORK_ORDERS_FOR_SELECT_RESOURCE } from '@/features/tasks/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'

/** `meta.rate` of `GET /vat-rates/for-select`: the percentage as decimal string. */
interface VatRateItem extends ForSelectItem {
  meta?: { rate: string | null }
}

interface PurchaseOrderLineRowProps {
  index: number
  control: Control<PurchaseOrderFormValues>
  setValue: UseFormSetValue<PurchaseOrderFormValues>
  line: PurchaseOrderLineFormValues
  /** Read-only order: every input is disabled and the actions disappear. */
  readOnly: boolean
  onRemove: () => void
  /** Full-width band under the fields (the detail's Annulla/Salva, spec 0257 D-7). */
  footer?: ReactNode
  className?: string
}

/**
 * One ODA line as a single compact band on desktop and a stacked card below
 * `lg`: product, description, cost type, U.m., quantity, price, VAT, the
 * preview total (taxable and VAT in its tooltip), work order and icon-only
 * actions (extract VAT, remove). A line generated from an RDA shows the
 * "RDA #id" badge and stays editable (D-18).
 */
export function PurchaseOrderLineRow({ index, control, setValue, line, readOnly, onRemove, footer, className }: PurchaseOrderLineRowProps) {
  const { t } = useTranslation()
  const n = index + 1
  const amounts = previewLineAmounts(line)
  const applyProduct = useOrderLineProductDefaults(index, setValue, line.cost_type_id)

  const handleExtractVat = () => {
    try {
      setValue(`lines.${index}.unit_price`, extractVat(line.unit_price, line.vat_rate_percent), {
        shouldDirty: true,
        shouldValidate: true,
      })
    } catch (error) {
      if (error instanceof MissingVatRateError) {
        toast.error(t('purchaseOrderForm.lines.extractVatNeedsRate'))
        return
      }
      throw error
    }
  }

  const total = formatQuoteAmount(amounts.total)
  const breakdown = `${t('purchaseOrderForm.lines.taxable')} ${formatQuoteAmount(amounts.taxable)}, ${t('purchaseOrderForm.lines.vat')} ${formatQuoteAmount(amounts.vat)}`
  const label = (key: string) => `${t(`purchaseOrderForm.lines.${key}`)} ${n}`

  return (
    <li className={cn(LINE_GRID, 'rounded-md border bg-card p-1.5', className)} aria-label={label('line')}>
      <div className={WIDE_CELL}>
        <LineProductSelect
          control={control}
          index={index}
          disabled={readOnly}
          label={label('product')}
          onProductPicked={(item) => (item ? void applyProduct(item.id) : undefined)}
        />
      </div>
      <div className={WIDE_CELL}>
        <LineDescriptionField control={control} index={index} disabled={readOnly} label={label('description')} />
      </div>
      <div className={WIDE_CELL}>
        <LineRelationSelect
          control={control}
          index={index}
          disabled={readOnly}
          label={label('costType')}
          name="cost_type_id"
          resource={COST_TYPES_FOR_SELECT_RESOURCE}
          placeholder={t('purchaseOrderForm.lines.costTypePlaceholder')}
          renderItem={costTypeOptionRenderer}
        />
      </div>
      <LineRelationSelect
        control={control}
        index={index}
        disabled={readOnly}
        label={label('unit')}
        name="unit_of_measure_id"
        resource={UNITS_OF_MEASURE_FOR_SELECT_RESOURCE}
        placeholder={t('purchaseOrderForm.lines.unitPlaceholder')}
      />
      <LineNumberField
        control={control}
        index={index}
        disabled={readOnly}
        name="quantity"
        label={label('quantity')}
        placeholder={t('purchaseOrderForm.lines.quantity')}
      />
      <LineNumberField
        control={control}
        index={index}
        disabled={readOnly}
        name="unit_price"
        label={label('unitPrice')}
        placeholder={t('purchaseOrderForm.lines.unitPrice')}
      />
      <LineRelationSelect
        control={control}
        index={index}
        disabled={readOnly}
        label={label('vatRate')}
        name="vat_rate_id"
        resource={VAT_RATES_FOR_SELECT_RESOURCE}
        placeholder={t('purchaseOrderForm.lines.vatPlaceholder')}
        onItemChange={(item) => {
          const rate = (item as VatRateItem | null)?.meta?.rate
          setValue(`lines.${index}.vat_rate_percent`, item && rate != null ? Number(rate) : null)
        }}
      />
      <div data-label={t('purchaseOrderForm.lines.total')} className={cn(CAPTIONED_CELL, 'lg:text-right')}>
        <Tooltip>
          <TooltipTrigger asChild>
            <span tabIndex={0} className="cursor-default font-medium" aria-label={`${label('total')}: ${total} (${breakdown})`}>
              {total}
            </span>
          </TooltipTrigger>
          <TooltipContent>{breakdown}</TooltipContent>
        </Tooltip>
      </div>
      <div className={WIDE_CELL}>
        <LineRelationSelect
          control={control}
          index={index}
          disabled={readOnly}
          label={label('workOrder')}
          name="work_order_id"
          resource={WORK_ORDERS_FOR_SELECT_RESOURCE}
          placeholder={t('purchaseOrderForm.lines.workOrderPlaceholder')}
        />
      </div>
      <div className="col-span-2 flex items-center justify-end gap-1 sm:col-span-4 lg:col-span-1">
        {line.purchase_request_id !== null ? (
          <Badge variant="secondary" className="mr-auto text-[11px]" title={t('purchaseOrderForm.lines.rdaBadgeLabel', { id: line.purchase_request_id })}>
            {t('purchaseOrderForm.lines.rdaBadge', { id: line.purchase_request_id })}
          </Badge>
        ) : null}
        {readOnly ? null : (
          <>
            <IconAction label={label('extractVat')} icon={<Calculator />} onClick={handleExtractVat} />
            <IconAction label={label('remove')} icon={<Trash2 />} className="text-destructive" onClick={onRemove} />
          </>
        )}
      </div>
      {footer ? <div className="col-span-full">{footer}</div> : null}
    </li>
  )
}

interface IconActionProps {
  label: string
  icon: ReactNode
  className?: string
  onClick: () => void
}

/** Icon-only action: the label is its accessible name and its tooltip. */
export function IconAction({ label, icon, className, onClick }: IconActionProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button type="button" variant="outline" size="icon-xs" className={cn('bg-card', className)} aria-label={label} onClick={onClick}>
          {icon}
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}
