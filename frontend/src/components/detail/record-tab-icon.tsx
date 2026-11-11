import type { ReactNode } from 'react'
import { IconCountBadge } from '@/components/ui/icon-count-badge'

interface RecordTabIconProps {
  icon: ReactNode
  /** `RecordCollaborationTab.badge`: usually a `RecordTabCount`, rendered on the icon's corner. */
  badge?: ReactNode
}

/**
 * A collaboration tab's icon with its count on the corner — the same in the
 * tab strip and in the collapsed rail. With a badge, the trailing space is a
 * text node of the flex host (never rendered there): it separates the count
 * from the label in the accessible name ("3 Note", not "3Note").
 */
export function RecordTabIcon({ icon, badge }: RecordTabIconProps) {
  return (
    <>
      <span className="relative inline-flex shrink-0">
        {icon}
        {badge}
      </span>
      {badge ? ' ' : null}
    </>
  )
}

/**
 * The count of a collaboration tab (notes, documents, time entries), on its
 * icon. Nothing while unknown (loading, error) or at zero, like the grid row
 * actions' badge.
 */
export function RecordTabCount({ count }: { count: number | undefined }) {
  return count ? <IconCountBadge count={count} className="-top-1.5 -right-1.5 h-3 min-w-3 text-[8px]" /> : null
}
