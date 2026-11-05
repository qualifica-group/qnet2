import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { History } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RecordCard, RecordCardHeader } from '@/components/detail/record-panel'
import { useConfirm } from '@/components/confirm-dialog-context'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { formatDate } from '@/lib/formatting/date-display'
import { useUndoContractRenewal } from '@/features/contracts/use-contract-mutations'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

interface RenewalFact {
  label: string
  value: string
}

interface PeriodRow {
  key: string
  title: string
  facts: RenewalFact[]
  undoRenewalId: number | null
}

interface ContractRenewalsSectionProps {
  contract: ContractDetailWithPermissions
  onChanged: (contract: ContractDetailWithPermissions) => void
}

/** Date shown as text, or the em dash the rest of the record uses for "no value". */
function dateOrDash(value: string | null): string {
  return (value ? formatDate(value) : '') || '—'
}

/** Builds one row per period: the initial one, then every renewal (spec 0358 D-15). */
function usePeriodRows(contract: ContractDetailWithPermissions): PeriodRow[] {
  const { t } = useTranslation()
  const { renewals } = contract
  const lastRenewal = renewals[renewals.length - 1]
  const canRenew = contract.permissions.actions.renew === true

  const initial: PeriodRow = {
    key: 'initial',
    title: t('contracts.renewals.initial'),
    facts: [
      { label: t('contracts.renewals.from'), value: dateOrDash(contract.accepted_at ?? contract.validated_at) },
      { label: t('contracts.renewals.to'), value: dateOrDash(renewals[0]?.previous_expiry_date ?? contract.expiry_date) },
      { label: t('contracts.renewals.workOrders'), value: String(contract.initial_period_work_orders_count) },
    ],
    undoRenewalId: null,
  }

  const rows = renewals.map<PeriodRow>((renewal) => ({
    key: `renewal-${renewal.id}`,
    title: t('contracts.renewals.renewal', { number: renewal.number }),
    facts: [
      { label: t('contracts.renewals.from'), value: dateOrDash(renewal.starts_on) },
      { label: t('contracts.renewals.to'), value: dateOrDash(renewal.expiry_date) },
      { label: t('contracts.renewals.renewalDate'), value: dateOrDash(renewal.renewal_date) },
      {
        label: t('contracts.renewals.previousDates'),
        value: t('contracts.renewals.previousDatesValue', {
          renewal: dateOrDash(renewal.previous_renewal_date),
          expiry: dateOrDash(renewal.previous_expiry_date),
        }),
      },
      { label: t('contracts.renewals.by'), value: renewal.renewed_by?.name ?? '—' },
      { label: t('contracts.renewals.workOrders'), value: String(renewal.work_orders_count) },
    ],
    undoRenewalId: renewal.id === lastRenewal?.id && renewal.can_undo && canRenew ? renewal.id : null,
  }))

  return [initial, ...rows]
}

/**
 * "Rinnovi" (spec 0358 D-15): the period history of the contract. Always
 * shown, with at least the "Iniziale" row, so the feature is discoverable
 * before the first renewal. "Annulla rinnovo" sits on the latest renewal only,
 * when the server says it can be undone and the actor may renew.
 */
export function ContractRenewalsSection({ contract, onChanged }: ContractRenewalsSectionProps) {
  const { t, i18n } = useTranslation()
  const confirm = useConfirm()
  const rows = usePeriodRows(contract)

  const undoMutation = useUndoContractRenewal({
    contractId: contract.id,
    onSuccess: (updated) => {
      toast.success(t('contracts.renewals.undoSuccess'))
      onChanged({ ...contract, ...updated })
    },
  })

  const handleUndo = async (renewalId: number) => {
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
      await undoMutation.mutateAsync(renewalId)
    } catch (error) {
      toast.error(apiErrorMessage(error, t('contracts.renewals.undoError'), i18n))
    }
  }

  return (
    <RecordCard>
      <RecordCardHeader
        title={
          <span className="flex items-center gap-2">
            <History className="size-4 text-muted-foreground" aria-hidden="true" />
            <span className="truncate">{t('contracts.renewals.title')}</span>
            <Badge variant="secondary">{contract.renewal_number}</Badge>
          </span>
        }
      />
      <ul className="divide-y">
        {rows.map((row) => (
          <li key={row.key} className="flex flex-col gap-1.5 px-4 py-2.5 text-xs @sm:flex-row @sm:items-start">
            <span className="w-24 shrink-0 text-sm font-semibold">{row.title}</span>
            <dl className="flex min-w-0 flex-1 flex-wrap gap-x-4 gap-y-1">
              {row.facts.map((fact) => (
                <div key={fact.label} className="flex min-w-0 gap-1">
                  <dt className="text-muted-foreground">{fact.label}:</dt>
                  <dd className="min-w-0 break-words font-medium">{fact.value}</dd>
                </div>
              ))}
            </dl>
            {row.undoRenewalId !== null ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="shrink-0 bg-card"
                disabled={undoMutation.isPending}
                onClick={() => void handleUndo(row.undoRenewalId as number)}
              >
                {t('contracts.renewals.undo')}
              </Button>
            ) : null}
          </li>
        ))}
      </ul>
    </RecordCard>
  )
}
