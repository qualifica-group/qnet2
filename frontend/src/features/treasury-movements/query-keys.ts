import type { SubAccountKind } from '@/features/treasury-movements/types'

/** Query keys of the treasury movements feature. */
export const treasuryMovementKeys = {
  detail: (id: number) => ['treasury-movements', 'detail', id] as const,
  originAccount: (id: number) => ['treasury-movements', 'origin-account', id] as const,
  subAccounts: (kind: SubAccountKind) => ['treasury-movements', 'sub-accounts', kind] as const,
}
