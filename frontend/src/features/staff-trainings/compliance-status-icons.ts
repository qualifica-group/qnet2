import { CircleCheck, CircleDashed, CircleX, TriangleAlert } from 'lucide-react'

export const COMPLIANCE_STATUS_ICON = {
  valid: CircleCheck,
  expiring: TriangleAlert,
  expired: CircleX,
  missing: CircleDashed,
} as const
