import type { ReactNode } from 'react'
import type { Control, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Calculator, ListChecks, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { LineDocumentsButton } from '@/features/purchase-requests/purchase-request-documents'
import {
  LineNumberField,
  LineProductSelect,
  LineTextField,
  LineUnitSelect,
  LineVatSelect,
} from '@/features/purchase-requests/purchase-request-line-inputs'
import { extractVat, MissingVatRateError, previewLineAmounts } from '@/features/purchase-requests/purchase-request-amounts'
import type {
  PurchaseRequestFormValues,
  PurchaseRequestLineFormValues,
} from '@/features/purchase-requests/purchase-request-schema'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import { useLineProductDefaults } from '@/features/purchase-requests/use-line-product-defaults'

/** Placeholder of the ODA column until the supplier-order module exists (spec 0208 D-3). */
const ODA_PLACEHOLDER = '—'

interface PurchaseRequestLineRowProps {
  index: number
  control: Control<PurchaseRequestFormValues>
  setValue: UseFormSetValue<PurchaseRequestFormValues>
  line: PurchaseRequestLineFormValues
  onRemove: () => void
  /** Opens the status change of this saved line; shown only when the server allows a transition. */
  onChangeStatus: () => void
}

function Readout({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex flex-col">
      <dt className="text-[11px] text-muted-foreground">{label}</dt>
      <dd className="text-xs tabular-nums">{children}</dd>
    </div>
  )
}

/**
 * One RDA line: product or free description with the reason below, U.m.,
 * quantity, price, VAT, the server-computed amounts as a read-only preview,
 * the status badge, documents, ODA placeholder and the row actions. A locked
 * line (non-pending, closed RDA, no update ability) shows everything disabled.
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

  const extractButton = disabled ? null : (
    <Button
      type="button"
      variant="secondary"
      size="xs"
      title={t('purchaseRequests.lines.extractVatHint')}
      aria-label={`${t('purchaseRequests.lines.extractVat')} ${n}`}
      onClick={handleExtractVat}
    >
      <Calculator aria-hidden="true" />
      {t('purchaseRequests.lines.extractVatShort')}
    </Button>
  )

  const showRemove = line.id === undefined || line.can_delete
  const showChangeStatus = line.id !== undefined && line.transitions.length > 0

  return (
    <li className="flex flex-col gap-2 rounded-md border bg-card p-2" aria-label={`${t('purchaseRequests.lines.line')} ${n}`}>
      <div className="grid gap-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
        <LineProductSelect
          control={control}
          index={index}
          disabled={disabled}
          label={`${t('purchaseRequests.lines.product')} ${n}`}
          onProductPicked={(productId) => void applyProduct(productId)}
        />
        <LineTextField
          control={control}
          index={index}
          disabled={disabled}
          name="description"
          label={`${t('purchaseRequests.lines.description')} ${n}`}
        />
      </div>
      <LineTextField
        control={control}
        index={index}
        disabled={disabled}
        name="reason"
        label={`${t('purchaseRequests.lines.reason')} ${n}`}
      />
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <LineUnitSelect control={control} index={index} disabled={disabled} label={`${t('purchaseRequests.lines.unit')} ${n}`} />
        <LineNumberField
          control={control}
          index={index}
          disabled={disabled}
          name="quantity"
          label={`${t('purchaseRequests.lines.quantity')} ${n}`}
        />
        <LineNumberField
          control={control}
          index={index}
          disabled={disabled}
          name="unit_price"
          label={`${t('purchaseRequests.lines.unitPrice')} ${n}`}
        />
        <LineVatSelect
          control={control}
          index={index}
          disabled={disabled}
          label={`${t('purchaseRequests.lines.vatRate')} ${n}`}
          onRateChange={(rate) => setValue(`lines.${index}.vat_rate_percent`, rate)}
        />
      </div>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <dl className="flex flex-wrap gap-x-5 gap-y-1">
          <Readout label={t('purchaseRequests.lines.taxable')}>{formatQuoteAmount(amounts.taxable)}</Readout>
          <Readout label={t('purchaseRequests.lines.vat')}>{formatQuoteAmount(amounts.vat)}</Readout>
          <Readout label={t('purchaseRequests.lines.total')}>
            <span className="font-medium">{formatQuoteAmount(amounts.total)}</span>
          </Readout>
          <Readout label={t('purchaseRequests.lines.status')}>
            <LineStatusBadge status={line.status} />
          </Readout>
          <Readout label={t('purchaseRequests.lines.oda')}>{ODA_PLACEHOLDER}</Readout>
        </dl>
        <div className="flex items-center gap-1.5">
          {showChangeStatus ? (
            <Button type="button" variant="secondary" size="xs" onClick={onChangeStatus}>
              <ListChecks aria-hidden="true" />
              {t('purchaseRequests.lines.changeStatus')}
            </Button>
          ) : null}
          {extractButton}
          <LineDocumentsButton control={control} index={index} lineId={line.id} pendingCount={line.pending_files.length} />
          {showRemove ? (
            <Button
              type="button"
              variant="outline"
              size="icon-xs"
              className="bg-card text-destructive"
              aria-label={`${t('purchaseRequests.lines.remove')} ${n}`}
              onClick={onRemove}
            >
              <Trash2 />
            </Button>
          ) : null}
        </div>
      </div>
    </li>
  )
}
