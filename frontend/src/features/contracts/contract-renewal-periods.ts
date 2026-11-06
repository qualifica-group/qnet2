import { useTranslation } from 'react-i18next'
import { formatDate } from '@/lib/formatting/date-display'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

const MS_PER_DAY = 86_400_000
const ISO_DATE_LENGTH = 10

export interface RenewalFact {
  label: string
  value: string
}

/** One period of the contract timeline: the initial one (number 0) or a renewal (spec 0358 D-15). */
export interface ContractPeriod {
  key: string
  number: number
  title: string
  range: string
  facts: RenewalFact[]
  isCurrent: boolean
  undoRenewalId: number | null
}

/** Date shown as text, or the em dash the rest of the record uses for "no value". */
export function dateOrDash(value: string | null): string {
  return (value ? formatDate(value) : '') || '—'
}

function dayIndex(value: string): number | null {
  const time = Date.parse(value.slice(0, ISO_DATE_LENGTH))
  return Number.isNaN(time) ? null : Math.round(time / MS_PER_DAY)
}

/**
 * Share of the current period already elapsed, 0-100. `daysToExpiry` is the
 * server's signed count (ContractAlertResolver), so "today" is the server's,
 * not the browser's. Null when a date is missing or the period has no length.
 */
export function periodElapsedPercent(startsOn: string | null, expiryDate: string | null, daysToExpiry: number | null): number | null {
  if (startsOn === null || expiryDate === null || daysToExpiry === null) {
    return null
  }
  const start = dayIndex(startsOn)
  const end = dayIndex(expiryDate)
  if (start === null || end === null || end <= start) {
    return null
  }
  const total = end - start
  const elapsed = Math.min(Math.max(total - daysToExpiry, 0), total)

  return Math.round((elapsed / total) * 100)
}

/** Start of the period in force: the latest renewal's, else the contract's acceptance/validation. */
export function currentPeriodStart(contract: ContractDetailWithPermissions): string | null {
  const lastRenewal = contract.renewals[contract.renewals.length - 1]

  return lastRenewal?.starts_on ?? contract.accepted_at ?? contract.validated_at
}

/**
 * The timeline of the contract: "Iniziale", then every renewal, oldest first.
 * "Annulla rinnovo" belongs to the latest renewal only, when the server says
 * it can be undone and the actor may renew.
 */
export function useContractPeriods(contract: ContractDetailWithPermissions): ContractPeriod[] {
  const { t } = useTranslation()
  const { renewals } = contract
  const lastRenewal = renewals[renewals.length - 1]
  const canRenew = contract.permissions.actions.renew === true
  const initialFrom = contract.accepted_at ?? contract.validated_at
  const initialTo = renewals[0]?.previous_expiry_date ?? contract.expiry_date

  const initial: ContractPeriod = {
    key: 'initial',
    number: 0,
    title: t('contracts.renewals.initial'),
    range: `${dateOrDash(initialFrom)} – ${dateOrDash(initialTo)}`,
    facts: [
      { label: t('contracts.renewals.from'), value: dateOrDash(initialFrom) },
      { label: t('contracts.renewals.to'), value: dateOrDash(initialTo) },
      { label: t('contracts.renewals.workOrders'), value: String(contract.initial_period_work_orders_count) },
    ],
    isCurrent: renewals.length === 0,
    undoRenewalId: null,
  }

  const periods = renewals.map<ContractPeriod>((renewal) => ({
    key: `renewal-${renewal.id}`,
    number: renewal.number,
    title: t('contracts.renewals.renewal', { number: renewal.number }),
    range: `${dateOrDash(renewal.starts_on)} – ${dateOrDash(renewal.expiry_date)}`,
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
      {
        label: t('contracts.renewals.by'),
        value: renewal.renewed_by
          ? t('contracts.renewals.byValue', { name: renewal.renewed_by.name, date: dateOrDash(renewal.created_at) })
          : '—',
      },
      { label: t('contracts.renewals.workOrders'), value: String(renewal.work_orders_count) },
    ],
    isCurrent: renewal.id === lastRenewal?.id,
    undoRenewalId: renewal.id === lastRenewal?.id && renewal.can_undo && canRenew ? renewal.id : null,
  }))

  return [initial, ...periods]
}
