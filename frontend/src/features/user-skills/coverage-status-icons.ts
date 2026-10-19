import { CircleCheck, CircleDashed, TriangleAlert } from 'lucide-react'

export const COVERAGE_STATUS_ICON = {
  covered: CircleCheck,
  below: TriangleAlert,
  missing: CircleDashed,
} as const
