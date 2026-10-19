import type { ComplianceParams } from '@/features/staff-trainings/types'

/** Centralized query keys of the staff trainings feature (spec 0284). */
export const staffTrainingKeys = {
  all: ['staff-trainings'] as const,
  detail: (id: number) => ['staff-trainings', 'detail', id] as const,
  compliance: (params: ComplianceParams) => ['staff-trainings', 'compliance', params] as const,
  complianceAll: ['staff-trainings', 'compliance'] as const,
}
