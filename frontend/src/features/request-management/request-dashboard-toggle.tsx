import { Can } from '@/features/auth/can'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'

export interface RequestDashboardToggleProps {
  domain: string
  isOpen: boolean
  onToggle: () => void
}

/**
 * Spec 0107 D-6: unlike every other module's `StatsToggleButton` (never
 * wrapped in `<Can>` because the whole page is already gated), this one IS
 * gated by `request-management.report` — the permission ships unassigned to
 * every role by default, and a button that only ever opens an error panel is
 * worse than no button. The backend stays the real authority regardless.
 */
export function RequestDashboardToggle({ domain, isOpen, onToggle }: RequestDashboardToggleProps) {
  return (
    <Can permission="request-management.report">
      <StatsToggleButton domain={domain} isOpen={isOpen} onToggle={onToggle} />
    </Can>
  )
}
