import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { managerSlotsFromRefs } from '@/lib/utils'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { updateWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { notifyWorkOrderSaveError } from '@/features/work-orders/work-order-save-errors'
import {
  clearParticipantSlot,
  hasFreeParticipantSlot,
  moveParticipantSlot,
  toggleParticipantId,
  toggleSupervisorId,
  type ParticipantSlots,
} from '@/features/work-orders/work-order-team-slots'
import type {
  UpdateWorkOrderPayload,
  WorkOrderDetailWithPermissions,
  WorkOrderParticipant,
} from '@/features/work-orders/types'

type TeamPayload = Pick<UpdateWorkOrderPayload, 'supervisor_ids' | 'participant_slots'>

interface TeamChange {
  payload: TeamPayload
  /** The payload that puts the team back as it was, for the toast's "Annulla"; absent on an undo itself. */
  undo?: TeamPayload
}

/** One row of the Partecipanti list: a filled slot or a gap that keeps the numbering. */
export interface ParticipantSlotRow {
  position: number
  participant: WorkOrderParticipant | null
}

function slotRows(participants: WorkOrderParticipant[]): ParticipantSlotRow[] {
  const byPosition = new Map(participants.map((participant) => [participant.position, participant]))
  return managerSlotsFromRefs(participants).map((_, index) => ({
    position: index + 1,
    participant: byPosition.get(index + 1) ?? null,
  }))
}

/**
 * The "Responsabili e Partecipanti" popup's editing (user request
 * 2026-11-27, "come i migliori CRM"): every add, removal or move is saved at
 * once as a PATCH of that one field, the saved record goes into the detail
 * cache (so the popup, the detail and its form re-sync on it), and the
 * success toast offers "Annulla". One change at a time: a click while a save
 * is running is ignored rather than computed from a stale team.
 */
export function useWorkOrderTeamEditor(workOrder: WorkOrderDetailWithPermissions, onMutated?: () => void) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const { field } = useResourcePermissions()
  const supervisorsField = field('supervisor_ids')
  const participantsField = field('participant_slots')

  const supervisorIds = workOrder.supervisors.map((supervisor) => supervisor.id)
  const slots: ParticipantSlots = managerSlotsFromRefs(workOrder.participants)

  const mutation = useMutation<WorkOrderDetailWithPermissions, unknown, TeamChange>({
    mutationFn: ({ payload }) => updateWorkOrder(workOrder.id, payload),
    onSuccess: (saved, { undo }) => {
      queryClient.setQueryData(workOrderDetailQueryKey(workOrder.id), saved)
      onMutated?.()
      if (undo === undefined) {
        toast.success(t('workOrders.actions.teamPanel.undone'))
        return
      }
      toast.success(t('workOrders.actions.teamPanel.saved'), {
        action: { label: t('workOrders.actions.teamPanel.undo'), onClick: () => mutation.mutate({ payload: undo }) },
      })
    },
    onError: (error) => notifyWorkOrderSaveError(error, t, i18n),
  })

  const saveSupervisors = (next: number[]) => {
    if (!mutation.isPending) {
      mutation.mutate({ payload: { supervisor_ids: next }, undo: { supervisor_ids: supervisorIds } })
    }
  }

  const saveParticipants = (next: ParticipantSlots | null) => {
    if (next !== null && !mutation.isPending) {
      mutation.mutate({ payload: { participant_slots: next }, undo: { participant_slots: slots } })
    }
  }

  return {
    supervisors: workOrder.supervisors,
    supervisorIds,
    participantRows: slotRows(workOrder.participants),
    participantIds: workOrder.participants.map((participant) => participant.id),
    showSupervisors: !supervisorsField.hidden,
    showParticipants: !participantsField.hidden,
    canEditSupervisors: supervisorsField.editable,
    canEditParticipants: participantsField.editable,
    canAddParticipant: hasFreeParticipantSlot(slots),
    isSaving: mutation.isPending,
    toggleSupervisor: (id: number) => saveSupervisors(toggleSupervisorId(supervisorIds, id)),
    toggleParticipant: (id: number) => saveParticipants(toggleParticipantId(slots, id)),
    clearParticipant: (position: number) => saveParticipants(clearParticipantSlot(slots, position)),
    moveParticipant: (position: number, delta: -1 | 1) => saveParticipants(moveParticipantSlot(slots, position, delta)),
  }
}

export type WorkOrderTeamEditor = ReturnType<typeof useWorkOrderTeamEditor>
