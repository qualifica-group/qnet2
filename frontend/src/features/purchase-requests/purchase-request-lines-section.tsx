import { useState } from 'react'
import type { Control, FieldArrayWithId, UseFormSetValue } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { LineStatusDialog } from '@/features/purchase-requests/line-status-dialog'
import { lineToTarget, type LineTarget } from '@/features/purchase-requests/line-status-transitions'
import { previewTotals } from '@/features/purchase-requests/purchase-request-amounts'
import { PurchaseRequestLineRow } from '@/features/purchase-requests/purchase-request-line-row'
import type {
  PurchaseRequestFormValues,
  PurchaseRequestLineFormValues,
} from '@/features/purchase-requests/purchase-request-schema'
import { PurchaseRequestTotals } from '@/features/purchase-requests/purchase-request-totals'
import type { PurchaseRequest } from '@/features/purchase-requests/types'

const NO_TARGETS: readonly LineTarget[] = []

interface PurchaseRequestLinesSectionProps {
  control: Control<PurchaseRequestFormValues>
  setValue: UseFormSetValue<PurchaseRequestFormValues>
  fields: FieldArrayWithId<PurchaseRequestFormValues, 'lines'>[]
  /** Live values of the lines (the preview and the per-row locks read from here). */
  lines: PurchaseRequestLineFormValues[]
  onAdd: () => void
  onRemove: (index: number) => void
  /** Saved RDA: source of the line summary shown by the status dialog. */
  request?: PurchaseRequest
  /** Closed RDA: no line can be added. */
  readOnly: boolean
  /** Root-level `lines` error (e.g. "at least one line"). */
  error?: string
}

/**
 * The RDA lines as a list of compact rows, with the add action, the live
 * totals and the per-line status change (approval / fulfilment, D-8). After a
 * change the detail query refetches and the page remounts the form.
 */
export function PurchaseRequestLinesSection({
  control,
  setValue,
  fields,
  lines,
  onAdd,
  onRemove,
  request,
  readOnly,
  error,
}: PurchaseRequestLinesSectionProps) {
  const { t } = useTranslation()
  const [targets, setTargets] = useState<readonly LineTarget[]>(NO_TARGETS)

  return (
    <section className="flex flex-col gap-3" aria-label={t('purchaseRequests.sections.lines')}>
      <div className="flex items-center justify-between gap-2">
        <h2 className="text-base font-semibold">{t('purchaseRequests.sections.lines')}</h2>
        {readOnly ? null : (
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onAdd}>
            <Plus aria-hidden="true" />
            {t('purchaseRequests.lines.add')}
          </Button>
        )}
      </div>
      {error ? (
        <p role="alert" className="text-xs text-destructive">
          {error}
        </p>
      ) : null}
      <ul className="flex flex-col gap-2">
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
              onChangeStatus={() => {
                const saved = request?.lines.find((candidate) => candidate.id === line.id)
                if (request && saved) {
                  setTargets([lineToTarget(saved, request, line.transitions)])
                }
              }}
            />
          ) : null
        })}
      </ul>
      <PurchaseRequestTotals totals={previewTotals(lines)} />
      <LineStatusDialog
        key={targets.map((target) => target.id).join(',')}
        targets={targets}
        onOpenChange={(open) => (open ? undefined : setTargets(NO_TARGETS))}
        onChanged={() => setTargets(NO_TARGETS)}
      />
    </section>
  )
}
