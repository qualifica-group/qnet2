import type { CoverageParams } from '@/features/user-skills/types'

/** Centralized query keys of the person skills feature (spec 0286). */
export const userSkillKeys = {
  all: ['user-skills'] as const,
  detail: (id: number) => ['user-skills', 'detail', id] as const,
  coverage: (params: CoverageParams) => ['user-skills', 'coverage', params] as const,
  coverageAll: ['user-skills', 'coverage'] as const,
}
