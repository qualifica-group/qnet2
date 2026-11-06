import type { ContractDetail } from '@/features/contracts/types'

/** Months added to today when the current period has no usable start or expiry (spec 0358 D-13). */
export const DEFAULT_RENEWAL_MONTHS = 12

const MS_PER_DAY = 86_400_000
const ISO_DATE_LENGTH = 10

export interface RenewalDefaults {
  /** `YYYY-MM-DD`. */
  expiry_date: string
  /** `YYYY-MM-DD`, or `''` when the contract has no renewal date to shift. */
  renewal_date: string
}

type RenewalDefaultsSource = Pick<
  ContractDetail,
  'renewals' | 'accepted_at' | 'validated_at' | 'renewal_date' | 'expiry_date'
>

function parseIsoDate(value: string): Date {
  return new Date(`${value.slice(0, ISO_DATE_LENGTH)}T00:00:00Z`)
}

function toIsoDate(date: Date): string {
  return date.toISOString().slice(0, ISO_DATE_LENGTH)
}

function addDays(value: string, days: number): string {
  return toIsoDate(new Date(parseIsoDate(value).getTime() + days * MS_PER_DAY))
}

/** Start of the CURRENT period: the latest renewal's `starts_on`, else acceptance, else validation. */
function currentPeriodStart(contract: RenewalDefaultsSource): string | null {
  const latest = contract.renewals[contract.renewals.length - 1]
  return latest?.starts_on ?? contract.accepted_at ?? contract.validated_at
}

/**
 * Pre-filled dates of the "Rinnova" dialog (spec 0358 D-13): the next period
 * lasts as long as the current one (start to expiry, both inclusive), so the
 * new expiry and renewal dates are the current ones shifted by that many days.
 * The user can edit both. `today` is injectable to keep the function pure.
 */
export function buildRenewalDefaults(contract: RenewalDefaultsSource, today: Date = new Date()): RenewalDefaults {
  const start = currentPeriodStart(contract)
  const expiry = contract.expiry_date

  if (start && expiry) {
    const days = Math.round((parseIsoDate(expiry).getTime() - parseIsoDate(start).getTime()) / MS_PER_DAY) + 1

    if (days > 0) {
      return {
        expiry_date: addDays(expiry, days),
        renewal_date: contract.renewal_date ? addDays(contract.renewal_date, days) : '',
      }
    }
  }

  const fallback = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth() + DEFAULT_RENEWAL_MONTHS, today.getUTCDate()))

  return { expiry_date: toIsoDate(fallback), renewal_date: '' }
}
