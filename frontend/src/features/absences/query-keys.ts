import type { AbsenceBalancesParams } from '@/features/absences/types'

/** Centralized query keys of the absences feature (specs 0278, 0279). */
export const absenceKeys = {
  all: ['absences'] as const,
  detail: (id: number) => ['absences', 'detail', id] as const,
  balances: (params: AbsenceBalancesParams = {}) => ['absences', 'balances', params] as const,
  balancesAll: ['absences', 'balances'] as const,
  typeOptions: ['absences', 'type-options'] as const,
}

/** Centralized query keys of the absence-types catalogue. */
export const absenceTypeKeys = {
  all: ['absence-types'] as const,
  detail: (id: number) => ['absence-types', 'detail', id] as const,
}
