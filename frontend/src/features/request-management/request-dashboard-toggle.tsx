import { Can } from '@/features/auth/can'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'

export interface RequestDashboardToggleProps {
  domain: string
  /** The caller's `module.permission('report')` (spec 0130): `request-management.report` or `enrollee-management.report`. */
  permission: string
  isOpen: boolean
  onToggle: () => void
}

/**
 * Spec 0107 D-6: unlike every other module's `StatsToggleButton` (never
 * wrapped in `<Can>` because the whole page is already gated), this one IS
 * gated by this module's own `.report` permission — it ships unassigned to
 * every role by default, and a button that only ever opens an error panel is
 * worse than no button. The backend stays the real authority regardless.
 */
export function RequestDashboardToggle({ domain, permission, isOpen, onToggle }: RequestDashboardToggleProps) {
  return (
    <Can permission={permission}>
      <StatsToggleButton domain={domain} isOpen={isOpen} onToggle={onToggle} />
    </Can>
  )
}
