import type { Control, FieldArrayWithId, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { TooltipProvider } from '@/components/ui/tooltip'
import { PurchaseOrderLineRow } from '@/features/purchase-orders/purchase-order-line-row'
import { PurchaseOrderLinesHeader } from '@/features/purchase-orders/purchase-order-lines-header'
import type {
  PurchaseOrderFormValues,
  PurchaseOrderLineFormValues,
} from '@/features/purchase-orders/purchase-order-schema'
import { PurchaseOrderTotals } from '@/features/purchase-orders/purchase-order-totals'
import { previewTotals } from '@/features/purchase-requests/purchase-request-amounts'

interface PurchaseOrderLinesSectionProps {
  control: Control<PurchaseOrderFormValues>
  setValue: UseFormSetValue<PurchaseOrderFormValues>
  fields: FieldArrayWithId<PurchaseOrderFormValues, 'lines'>[]
  /** Live values of the lines (the preview reads from here). */
  lines: PurchaseOrderLineFormValues[]
  onAdd: () => void
  onRemove: (index: number) => void
  /** Root-level `lines` error (e.g. "at least one line"). */
  error?: string
}

/**
 * The lines of a NEW ODA as a header plus compact editable rows, with the add
 * action and the live totals preview. A saved ODA edits its lines one at a
 * time on the detail instead (`PurchaseOrderLinesRecord`, spec 0257 D-7).
 */
export function PurchaseOrderLinesSection({
  control,
  setValue,
  fields,
  lines,
  onAdd,
  onRemove,
  error,
}: PurchaseOrderLinesSectionProps) {
  const { t } = useTranslation()

  return (
    <section className="flex flex-col gap-3" aria-label={t('purchaseOrderForm.sections.lines')}>
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t('purchaseOrderForm.sections.lines')}
        </h3>
        <Button type="button" variant="secondary" size="xs" onClick={onAdd}>
          <Plus aria-hidden="true" />
          {t('purchaseOrderForm.lines.add')}
        </Button>
      </div>
      {error ? (
        <p role="alert" className="text-xs text-destructive">
          {error}
        </p>
      ) : null}
      <TooltipProvider>
        <div className="overflow-x-auto">
          <div className="flex flex-col gap-1.5 lg:min-w-[62rem]">
            <PurchaseOrderLinesHeader />
            <ul className="flex flex-col gap-1.5">
              {fields.map((field, index) => {
                const line = lines[index]
                return line ? (
                  <PurchaseOrderLineRow
                    key={field.id}
                    index={index}
                    control={control}
                    setValue={setValue}
                    line={line}
                    readOnly={false}
                    onRemove={() => onRemove(index)}
                  />
                ) : null
              })}
            </ul>
          </div>
        </div>
      </TooltipProvider>
      <PurchaseOrderTotals totals={previewTotals(lines)} />
    </section>
  )
}
