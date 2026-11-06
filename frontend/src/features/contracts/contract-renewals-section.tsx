import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { CalendarClock, History, RefreshCw } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { useConfirm } from '@/components/confirm-dialog-context'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { cn } from '@/lib/utils'
import { useUndoContractRenewal } from '@/features/contracts/use-contract-mutations'
import {
  currentPeriodStart,
  dateOrDash,
  periodElapsedPercent,
  useContractPeriods,
  type ContractPeriod,
} from '@/features/contracts/contract-renewal-periods'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

interface ContractRenewalsSectionProps {
  contract: ContractDetailWithPermissions
  onChanged: (contract: ContractDetailWithPermissions) => void
}

type DeadlineTone = 'danger' | 'warning' | 'neutral'

const TONE_TEXT_CLASS: Record<DeadlineTone, string> = {
  danger: 'text-destructive',
  warning: 'text-warning',
  neutral: 'text-muted-foreground',
}

const TONE_BAR_CLASS: Record<DeadlineTone, string> = {
  danger: 'bg-destructive',
  warning: 'bg-warning',
  neutral: 'bg-primary',
}

/** Past expiry is red; the server's `expiring` alert is amber (no client-side threshold). */
function expiryTone(contract: ContractDetailWithPermissions): DeadlineTone {
  if (contract.days_to_expiry !== null && contract.days_to_expiry < 0) {
    return 'danger'
  }
  return contract.alert === 'expiring' ? 'warning' : 'neutral'
}

function useUndoRenewal(contract: ContractDetailWithPermissions, onChanged: ContractRenewalsSectionProps['onChanged']) {
  const { t, i18n } = useTranslation()
  const confirm = useConfirm()
  const mutation = useUndoContractRenewal({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.renewals.undoSuccess'))
      onChanged({ ...contract, ...updated })
    },
  })

  const undo = async (renewalId: number) => {
    const confirmed = await confirm({
      title: t('contracts.renewals.undo'),
      description: t('contracts.renewals.undoDescription'),
      confirmLabel: t('contracts.renewals.undoConfirm'),
      tone: 'destructive',
    })
    if (!confirmed) {
      return
    }
    try {
      await mutation.mutateAsync(renewalId)
    } catch (error) {
      toast.error(apiErrorMessage(error, t('contracts.renewals.undoError'), i18n))
    }
  }

  return { undo, isPending: mutation.isPending }
}

/** Countdowns of the period in force: expiry (signed, from the server) and the renewal deadline. */
function PeriodDeadlines({ contract }: { contract: ContractDetailWithPermissions }) {
  const { t } = useTranslation()
  const days = contract.days_to_expiry
  let expiryLabel: string | null = null
  if (days !== null) {
    expiryLabel =
      days < 0
        ? t('contracts.renewals.expiredAgo', { count: -days })
        : days === 0
          ? t('contracts.renewals.expiresToday')
          : t('contracts.renewals.expiresIn', { count: days })
  }

  return (
    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
      {expiryLabel ? (
        <span className={cn('flex items-center gap-1 font-medium', TONE_TEXT_CLASS[expiryTone(contract)])}>
          <CalendarClock className="size-3.5" aria-hidden="true" />
          {expiryLabel}
        </span>
      ) : null}
      {contract.renewal_date ? (
        <span
          className={cn(
            'flex items-center gap-1',
            contract.alert === 'renewal_due' ? 'font-medium text-warning' : 'text-muted-foreground',
          )}
        >
          <RefreshCw className="size-3.5" aria-hidden="true" />
          {t('contracts.renewals.renewBy', { date: dateOrDash(contract.renewal_date) })}
        </span>
      ) : null}
    </div>
  )
}

/** Horizontal stepper of the periods; the pressed node drives the detail panel below. */
function PeriodTimeline({
  periods,
  selectedKey,
  onSelect,
}: {
  periods: ContractPeriod[]
  selectedKey: string
  onSelect: (key: string) => void
}) {
  const { t } = useTranslation()

  return (
    <ol aria-label={t('contracts.renewals.timeline')} className="-mx-1 flex items-center overflow-x-auto px-1 py-0.5">
      {periods.map((period, index) => {
        const selected = period.key === selectedKey
        return (
          <li key={period.key} className="flex shrink-0 items-center">
            {index > 0 ? <span aria-hidden="true" className="h-px w-3 bg-border @md:w-6" /> : null}
            <button
              type="button"
              aria-pressed={selected}
              onClick={() => onSelect(period.key)}
              className={cn(
                'flex flex-col items-start rounded-lg border px-2.5 py-1 text-left transition-colors',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                selected ? 'border-primary bg-primary/10' : 'bg-card hover:bg-muted/40',
              )}
            >
              <span className="flex items-center gap-1.5 text-xs font-semibold text-foreground">
                {period.isCurrent ? <span aria-hidden="true" className="size-1.5 rounded-full bg-primary" /> : null}
                {period.title}
                {period.isCurrent ? <span className="sr-only">({t('contracts.renewals.current')})</span> : null}
              </span>
              <span className="text-[11px] whitespace-nowrap text-muted-foreground tabular-nums">{period.range}</span>
            </button>
          </li>
        )
      })}
    </ol>
  )
}

/**
 * "Rinnovi" (spec 0358 D-15), the contract's term panel: it sits in the
 * record card right under the actions bar (user directive 2026-11-05), so
 * the period in force, its countdowns and its progress are read before
 * anything else. The timeline always carries at least "Iniziale", so the
 * feature is discoverable before the first renewal; the selected period's
 * facts (and "Annulla rinnovo" on the latest renewal) open below it.
 */
export function ContractRenewalsSection({ contract, onChanged }: ContractRenewalsSectionProps) {
  const { t } = useTranslation()
  const periods = useContractPeriods(contract)
  const current = periods[periods.length - 1]
  const [pickedKey, setPickedKey] = useState<string | null>(null)
  // A period removed by "Annulla rinnovo" falls back to the one in force.
  const selected = periods.find((period) => period.key === pickedKey) ?? current
  const { undo, isPending } = useUndoRenewal(contract, onChanged)
  const elapsed = periodElapsedPercent(currentPeriodStart(contract), contract.expiry_date, contract.days_to_expiry)

  return (
    <section className="flex flex-col gap-2.5 border-b px-4 py-3">
      <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <h3
          className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
        >
          <History className="size-3.5" aria-hidden="true" />
          {t('contracts.renewals.title')}
          <Badge variant="secondary" className="normal-case">
            {t('contracts.renewals.currentPeriod', { period: current.title })}
          </Badge>
        </h3>
        <PeriodDeadlines contract={contract} />
      </div>

      {elapsed !== null ? (
        <div className="flex items-center gap-2">
          <Progress
            value={elapsed}
            size="xs"
            aria-label={t('contracts.renewals.elapsed', { percent: elapsed })}
            indicatorClassName={TONE_BAR_CLASS[expiryTone(contract)]}
          />
          <span className="shrink-0 text-[11px] text-muted-foreground tabular-nums">
            {t('contracts.renewals.elapsed', { percent: elapsed })}
          </span>
        </div>
      ) : null}

      <PeriodTimeline periods={periods} selectedKey={selected.key} onSelect={setPickedKey} />

      <div className="flex flex-col gap-2 rounded-lg bg-muted/40 px-3 py-2 @md:flex-row @md:items-start">
        <dl
          aria-label={selected.title}
          className="grid min-w-0 flex-1 grid-cols-2 gap-x-4 gap-y-1.5 @md:grid-cols-3 @3xl:grid-cols-6"
        >
          {selected.facts.map((fact) => (
            <div key={fact.label} className="flex min-w-0 flex-col">
              <dt className="truncate text-[11px] text-muted-foreground">{fact.label}</dt>
              <dd className="min-w-0 text-xs font-medium break-words text-foreground">{fact.value}</dd>
            </div>
          ))}
        </dl>
        {selected.undoRenewalId !== null ? (
          <Button
            type="button"
            size="sm"
            variant="outline"
            className="shrink-0 self-start bg-card"
            disabled={isPending}
            onClick={() => void undo(selected.undoRenewalId as number)}
          >
            {t('contracts.renewals.undo')}
          </Button>
        ) : null}
      </div>
    </section>
  )
}
