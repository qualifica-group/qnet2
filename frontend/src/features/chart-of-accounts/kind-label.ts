import type { TFunction } from 'i18next'
import type { ChartAccountKind } from '@/features/chart-of-accounts/types'

/** Cost / Revenue labels live in `chartOfAccounts.kind`; the financial one (spec 0355) in `treasuryTypes.kind`. */
export function chartKindLabel(t: TFunction, kind: ChartAccountKind): string {
  return kind === 'financial' ? t('treasuryTypes.kind.financial') : t(`chartOfAccounts.kind.${kind}`)
}
