import { useTranslation } from 'react-i18next'
import { Calculator, CircleCheck, RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { calculateCommissionAmount } from './commission-calculator'
import { formatQuoteAmount } from './quote-summary'
import type { QuoteLineCommission, QuoteLineCommissionInput } from './types'

interface QuoteCommissionSystemDefaultProps {
  /** The rules' own commission for this role; `null` = the configuration grants none. */
  systemDefault: QuoteLineCommission | null
  /** The row's current commission for this role, `undefined` when it has none. */
  current: QuoteLineCommissionInput | undefined
  /** The same base the row's own calculated amount uses (spec 0145 D-1/D-2). */
  commissionBase: number
  canApply: boolean
  onApply: (systemDefault: QuoteLineCommission) => void
}

/** True when the row's commission already matches what the rules calculate. */
function matchesSystemDefault(current: QuoteLineCommissionInput, systemDefault: QuoteLineCommission): boolean {
  return current.commission_type === systemDefault.commission_type
    && current.value === Number(systemDefault.value)
}

/**
 * What the Configuratore Commissioni would assign this role, shown even when
 * the row carries a manual override (user directive 2026-10-07), so the
 * operator can compare and decide whether to keep the override or go back.
 */
export function QuoteCommissionSystemDefault({
  systemDefault,
  current,
  commissionBase,
  canApply,
  onApply,
}: QuoteCommissionSystemDefaultProps) {
  const { t } = useTranslation()

  if (systemDefault === null) {
    return (
      <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
        <Calculator aria-hidden="true" className="size-3.5 shrink-0" />
        {t('quotes.form.commissions.systemDefault.none')}
      </p>
    )
  }

  const value = Number(systemDefault.value)
  const amount = calculateCommissionAmount(systemDefault.commission_type, value, commissionBase)
  const inUse = current !== undefined && matchesSystemDefault(current, systemDefault)

  return (
    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-muted-foreground">
      <Calculator aria-hidden="true" className="size-3.5 shrink-0" />
      <span>{t('quotes.form.commissions.systemDefault.label')}:</span>
      <span className="font-medium text-foreground tabular-nums">
        {systemDefault.commission_type === 'PERCENTAGE' ? `${formatQuoteAmount(value)}%` : `${formatQuoteAmount(value)} €`}
        {' · '}
        {formatQuoteAmount(amount)}
      </span>
      <span>({t(`quotes.form.commissions.origins.${systemDefault.origin}`)})</span>
      {inUse ? (
        <span className="inline-flex items-center gap-1 font-medium text-success">
          <CircleCheck aria-hidden="true" className="size-3.5" />
          {t('quotes.form.commissions.systemDefault.inUse')}
        </span>
      ) : canApply ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-6 gap-1 bg-card px-2 text-[11px]"
          onClick={() => onApply(systemDefault)}
        >
          <RotateCcw aria-hidden="true" className="size-3" />
          {t('quotes.form.commissions.systemDefault.apply')}
        </Button>
      ) : null}
    </div>
  )
}
