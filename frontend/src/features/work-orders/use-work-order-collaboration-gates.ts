import { useAbilities } from '@/features/auth/use-abilities'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

interface WorkOrderCollaborationGates {
  canViewNotes: boolean
  canViewDocuments: boolean
  canViewActivity: boolean
  /** False = the whole card is absent, so the layout must not reserve a side column for it. */
  hasAny: boolean
}

/**
 * Per-tab authorization, each from its OWN source (spec 0134 D-3). Read both
 * by the card and by the detail layout, which cannot ask the card whether it
 * rendered.
 *
 * Notes are gated on the resource permission alone: the membership half of the
 * server rule (`WorkOrderNotable::authorizeRead`) is not evaluable client-side,
 * and `NotesSection` owns its error state for that residual case.
 */
export function useWorkOrderCollaborationGates(
  workOrder: WorkOrderDetailWithPermissions,
): WorkOrderCollaborationGates {
  const { can } = useAbilities()

  const canViewNotes = can('work-orders.view')
  const canViewDocuments = workOrder.permissions.actions.view_documents
  const canViewActivity = workOrder.permissions.actions.view_activity

  return {
    canViewNotes,
    canViewDocuments,
    canViewActivity,
    hasAny: canViewNotes || canViewDocuments || canViewActivity,
  }
}
