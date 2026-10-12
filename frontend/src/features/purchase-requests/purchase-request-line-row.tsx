import type { Control, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { OdaLink } from '@/features/purchase-requests/oda-link'
import { LineActions } from '@/features/purchase-requests/purchase-request-line-actions'
import {
  LineNumberField,
  LineProductSelect,
  LineTextField,
  LineUnitSelect,
  LineVatSelect,
} from '@/features/purchase-requests/purchase-request-line-inputs'
import { CAPTIONED_CELL, LINE_GRID, WIDE_CELL } from '@/features/purchase-requests/purchase-request-line-layout'
import { extractVat, MissingVatRateError, previewLineAmounts } from '@/features/purchase-requests/purchase-request-amounts'
import type {
  PurchaseRequestFormValues,
  PurchaseRequestLineFormValues,
} from '@/features/purchase-requests/purchase-request-schema'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import { LineSupplierSelect } from '@/features/purchase-requests/purchase-request-supplier'
import { useLineProductDefaults } from '@/features/purchase-requests/use-line-product-defaults'

interface PurchaseRequestLineRowProps {
  index: number
  control: Control<PurchaseRequestFormValues>
  setValue: UseFormSetValue<PurchaseRequestFormValues>
  line: PurchaseRequestLineFormValues
  onRemove: () => void
  /** Opens the status change of this saved line; shown only when the server allows a transition. */
  onChangeStatus: () => void
}

/**
 * One RDA line as a single compact band on desktop and a stacked card below
 * `lg` (spec 0209 D-9): product, description, supplier, U.m., quantity, price,
 * VAT, the server-computed total (taxable and VAT in its tooltip), status, ODA
 * placeholder and icon-only actions. A locked line (non-pending, closed RDA, no
 * update ability) shows everything disabled, except the supplier when the
 * server says it can still change (`can_update_supplier`, AC-013).
 */
export function PurchaseRequestLineRow({
  index,
  control,
  setValue,
  line,
  onRemove,
  onChangeStatus,
}: PurchaseRequestLineRowProps) {
  const { t } = useTranslation()
  const n = index + 1
  const disabled = line.locked
  const amounts = previewLineAmounts(line)
  const applyProduct = useLineProductDefaults(index, setValue)

  const handleExtractVat = () => {
    try {
      setValue(`lines.${index}.unit_price`, extractVat(line.unit_price, line.vat_rate_percent), {
        shouldDirty: true,
        shouldValidate: true,
      })
    } catch (error) {
      if (error instanceof MissingVatRateError) {
        toast.error(t('purchaseRequests.lines.extractVatNeedsRate'))
        return
      }
      throw error
    }
  }

  const total = formatQuoteAmount(amounts.total)
  const breakdown = `${t('purchaseRequests.lines.taxable')} ${formatQuoteAmount(amounts.taxable)}, ${t('purchaseRequests.lines.vat')} ${formatQuoteAmount(amounts.vat)}`

  return (
    <li className={cn(LINE_GRID, 'rounded-md border bg-card p-1.5')} aria-label={`${t('purchaseRequests.lines.line')} ${n}`}>
      <div className={WIDE_CELL}>
        <LineProductSelect
          control={control}
          index={index}
          disabled={disabled}
          label={`${t('purchaseRequests.lines.product')} ${n}`}
          onProductPicked={(productId) => void applyProduct(productId)}
        />
      </div>
      <div className={WIDE_CELL}>
        <LineTextField
          control={control}
          index={index}
          disabled={disabled}
          label={`${t('purchaseRequests.lines.description')} ${n}`}
        />
      </div>
      <div className={WIDE_CELL}>
        <LineSupplierSelect
          control={control}
          setValue={setValue}
          index={index}
          selected={line.supplier_ref}
          disabled={disabled && !line.can_update_supplier}
          label={`${t('purchaseRequests.lines.supplier')} ${n}`}
        />
      </div>
      <LineUnitSelect control={control} index={index} disabled={disabled} label={`${t('purchaseRequests.lines.unit')} ${n}`} />
      <LineNumberField
        control={control}
        index={index}
        disabled={disabled}
        name="quantity"
        label={`${t('purchaseRequests.lines.quantity')} ${n}`}
        placeholder={t('purchaseRequests.lines.quantity')}
      />
      <LineNumberField
        control={control}
        index={index}
        disabled={disabled}
        name="unit_price"
        label={`${t('purchaseRequests.lines.unitPrice')} ${n}`}
        placeholder={t('purchaseRequests.lines.unitPrice')}
      />
      <LineVatSelect
        control={control}
        index={index}
        disabled={disabled}
        label={`${t('purchaseRequests.lines.vatRate')} ${n}`}
        onRateChange={(rate) => setValue(`lines.${index}.vat_rate_percent`, rate)}
      />
      <div data-label={t('purchaseRequests.lines.total')} className={cn(CAPTIONED_CELL, 'lg:text-right')}>
        <Tooltip>
          <TooltipTrigger asChild>
            <span tabIndex={0} className="cursor-default font-medium" aria-label={`${t('purchaseRequests.lines.total')} ${n}: ${total} (${breakdown})`}>
              {total}
            </span>
          </TooltipTrigger>
          <TooltipContent>{breakdown}</TooltipContent>
        </Tooltip>
      </div>
      <div data-label={t('purchaseRequests.lines.status')} className={CAPTIONED_CELL}>
        <LineStatusBadge status={line.status} />
      </div>
      <div data-label={t('purchaseRequests.lines.oda')} className={cn(CAPTIONED_CELL, 'col-span-2 sm:col-span-1')}>
        <OdaLink purchaseOrder={line.purchase_order} odaReference={line.oda_reference} />
      </div>
      <div className="col-span-2 sm:col-span-4 lg:col-span-1">
        <LineActions
          control={control}
          index={index}
          lineId={line.id}
          locked={disabled}
          hasReason={line.reason.trim() !== ''}
          pendingFiles={line.pending_files.length}
          showChangeStatus={line.id !== undefined && line.transitions.length > 0}
          showRemove={line.id === undefined || line.can_delete}
          onChangeStatus={onChangeStatus}
          onExtractVat={handleExtractVat}
          onRemove={onRemove}
        />
      </div>
    </li>
  )
}
