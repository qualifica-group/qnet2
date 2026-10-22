import type {
  AbsenceBalancesParams,
  AbsenceCalendarParams,
  PreviewAbsenceRequestPayload,
} from '@/features/absences/types'

/** Centralized query keys of the absences feature (specs 0278, 0279). */
export const absenceKeys = {
  all: ['absences'] as const,
  detail: (id: number) => ['absences', 'detail', id] as const,
  balances: (params: AbsenceBalancesParams = {}) => ['absences', 'balances', params] as const,
  balancesAll: ['absences', 'balances'] as const,
  typeOptions: ['absences', 'type-options'] as const,
  calendar: (params: AbsenceCalendarParams) => ['absences', 'calendar', params] as const,
  calendarAll: ['absences', 'calendar'] as const,
  insight: (id: number) => ['absences', 'insight', id] as const,
  insightAll: ['absences', 'insight'] as const,
  preview: (payload: PreviewAbsenceRequestPayload) => ['absences', 'preview', payload] as const,
  summary: ['absences', 'summary'] as const,
}

/** Centralized query keys of the absence-types catalogue. */
export const absenceTypeKeys = {
  all: ['absence-types'] as const,
  detail: (id: number) => ['absence-types', 'detail', id] as const,
}
