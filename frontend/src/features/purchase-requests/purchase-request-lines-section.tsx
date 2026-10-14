import type { Control, FieldArrayWithId, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { TooltipProvider } from '@/components/ui/tooltip'
import { previewTotals } from '@/features/purchase-requests/purchase-request-amounts'
import { PurchaseRequestLineRow } from '@/features/purchase-requests/purchase-request-line-row'
import { PurchaseRequestLinesHeader } from '@/features/purchase-requests/purchase-request-lines-header'
import type {
  PurchaseRequestFormValues,
  PurchaseRequestLineFormValues,
} from '@/features/purchase-requests/purchase-request-schema'
import { PurchaseRequestTotals } from '@/features/purchase-requests/purchase-request-totals'

interface PurchaseRequestLinesSectionProps {
  control: Control<PurchaseRequestFormValues>
  setValue: UseFormSetValue<PurchaseRequestFormValues>
  fields: FieldArrayWithId<PurchaseRequestFormValues, 'lines'>[]
  /** Live values of the lines (the preview reads from here). */
  lines: PurchaseRequestLineFormValues[]
  onAdd: () => void
  onRemove: (index: number) => void
  /** Root-level `lines` error (e.g. "at least one line"). */
  error?: string
}

/**
 * The lines of a NEW RDA as a header plus compact editable rows, with the add
 * action and the live totals. A saved RDA edits its lines one at a time on the
 * detail instead (`PurchaseRequestLinesRecord`, spec 0256 D-7).
 */
export function PurchaseRequestLinesSection({
  control,
  setValue,
  fields,
  lines,
  onAdd,
  onRemove,
  error,
}: PurchaseRequestLinesSectionProps) {
  const { t } = useTranslation()

  return (
    <section className="flex flex-col gap-3" aria-label={t('purchaseRequests.sections.lines')}>
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t('purchaseRequests.sections.lines')}
        </h3>
        <Button type="button" variant="secondary" size="xs" onClick={onAdd}>
          <Plus aria-hidden="true" />
          {t('purchaseRequests.lines.add')}
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
            <PurchaseRequestLinesHeader />
            <ul className="flex flex-col gap-1.5">
              {fields.map((field, index) => {
                const line = lines[index]
                return line ? (
                  <PurchaseRequestLineRow
                    key={field.id}
                    index={index}
                    control={control}
                    setValue={setValue}
                    line={line}
                    onRemove={() => onRemove(index)}
                    onChangeStatus={() => undefined}
                  />
                ) : null
              })}
            </ul>
          </div>
        </div>
      </TooltipProvider>
      <PurchaseRequestTotals totals={previewTotals(lines)} />
    </section>
  )
}
