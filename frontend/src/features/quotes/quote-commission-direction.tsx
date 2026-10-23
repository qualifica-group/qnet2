import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import { SegmentedControl, type SegmentedControlOption } from '@/components/ui/segmented-control'
import { SUPPLIER_COMMISSION_DIRECTIONS, type SupplierCommissionDirection } from '@/features/commission-configurations/types'
import type { QuoteCommissionsDraft } from './use-quote-commissions-draft'
import type { QuoteLineCommissionInput } from './types'

interface QuoteCommissionDirectionProps {
  commission: QuoteLineCommissionInput
  commissions: QuoteCommissionsDraft
  disabled: boolean
}

/**
 * Spec 0306 D-3: which way the line's Supplier commission goes. An automatic
 * one shows the direction its rule gave (read only); a manual one is chosen
 * here, Received or Paid, and cannot be saved without it. Governed by the
 * `commission_type` field permission (D-8).
 */
export function QuoteCommissionDirection({ commission, commissions, disabled }: QuoteCommissionDirectionProps) {
  const { t } = useTranslation()
  const labelId = useId()
  const errorId = useId()
  const manual = commission.origin === 'MANUAL_OVERRIDE'
  const direction = manual ? commission.supplier_commission_direction ?? null : commissions.rowDirection
  const editable = manual && !disabled && commissions.canMutate && commissions.fields.direction.editable
  const missing = manual && direction === null

  if (!commissions.fields.direction.visible || (!manual && direction === null)) {
    return null
  }

  const options: SegmentedControlOption<SupplierCommissionDirection>[] = SUPPLIER_COMMISSION_DIRECTIONS.map((value) => ({
    value,
    label: t(`commissionConfigurations.options.supplier_commission_direction.${value}`),
  }))

  return (
    <div className="flex flex-col gap-1 md:pl-[2.625rem]">
      <div className="flex flex-wrap items-center gap-2">
        <Label id={labelId} className="text-[11px] font-normal text-muted-foreground">
          {t('quotes.form.commissions.direction.label')}
        </Label>
        {editable ? (
          <SegmentedControl
            aria-labelledby={labelId}
            aria-invalid={missing || undefined}
            aria-describedby={missing ? errorId : undefined}
            value={direction}
            options={options}
            onValueChange={(value) => commissions.patch('SUPPLIER', { supplier_commission_direction: value })}
            className="min-w-40 flex-nowrap p-0.5"
          />
        ) : (
          <span aria-labelledby={labelId} className="text-xs font-medium">
            {direction ? t(`commissionConfigurations.options.supplier_commission_direction.${direction}`) : '—'}
          </span>
        )}
        <span className="text-[11px] text-muted-foreground">
          {t(manual ? 'quotes.form.commissions.direction.manualHint' : 'quotes.form.commissions.direction.ruleHint')}
        </span>
      </div>
      {missing ? (
        <p id={errorId} role="alert" className="text-xs text-destructive">
          {t('quotes.form.commissions.direction.required')}
        </p>
      ) : null}
    </div>
  )
}
