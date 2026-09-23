import { History } from 'lucide-react'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'

export const ACTIVITY_LOG_TAB = 'activity'

/**
 * The Attivita' tab of a record's collaboration card. The caller pushes it
 * only when the record's `permissions.actions.view_activity` allows it.
 */
export function activityLogTab(resource: string, id: number, label: string): RecordCollaborationTab {
  return {
    value: ACTIVITY_LOG_TAB,
    label,
    icon: <History className="size-3.5" aria-hidden="true" />,
    content: <ActivityLogSection resource={resource} id={id} />,
  }
}
