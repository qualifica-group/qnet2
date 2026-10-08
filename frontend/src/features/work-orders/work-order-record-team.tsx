import { useTranslation } from 'react-i18next'
import { Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RECORD_PERSON_ROW_CLASS, RecordPerson } from '@/components/detail/record-person'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { WorkOrderParticipantsField, WorkOrderSupervisorsField } from '@/features/work-orders/work-order-relation-fields'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'
import type { WorkOrderParticipant, WorkOrderSupervisor } from '@/features/work-orders/types'

interface WorkOrderTeamSectionProps {
  /** Responsabili, as the record (or the draft's resolved labels) names them. */
  supervisors: WorkOrderSupervisor[]
  /** Partecipanti with their 1-based slot; gaps are meaningful. */
  participants: WorkOrderParticipant[]
  form: WorkOrderFormState
  inline: InlineEdit
}

/**
 * The commessa's Team block, with the SAME `RecordPerson` the
 * Opportunita'/Offerta/Anagrafica records use (avatar + hover card, a click
 * opens the shared user modal). Two in-place rows (spec 0195 applied to
 * Commesse): Responsabili, an unordered set, and Partecipanti, the ordered
 * slots — one editor for all of them, since moving a person between slots is
 * one change. Each participant keeps its "Partecipante n" denomination.
 */
export function WorkOrderTeamSection({ supervisors, participants, form, inline }: WorkOrderTeamSectionProps) {
  const { t } = useTranslation()
  const { control } = form.form
  const sortedParticipants = [...participants].sort((a, b) => a.position - b.position)

  return (
    <RecordSection title={t('workOrders.detail.sections.team')} icon={<Users />}>
      <RecordFieldList>
        <RecordInlineField
          field="supervisor_ids"
          label={t('workOrders.detail.supervisors')}
          inline={inline}
          className={RECORD_PERSON_ROW_CLASS}
          editor={<WorkOrderSupervisorsField control={control} selected={supervisors} />}
        >
          {supervisors.length > 0 ? (
            <ul className="flex min-w-0 flex-col gap-1">
              {supervisors.map((supervisor) => (
                <li key={supervisor.id} className="min-w-0">
                  <RecordPerson user={supervisor} />
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="participant_slots"
          label={t('workOrders.detail.participants')}
          inline={inline}
          editor={
            <WorkOrderParticipantsField
              control={control}
              selected={participants.map((participant) => ({ id: participant.id, label: participant.name }))}
            />
          }
        >
          {sortedParticipants.length > 0 ? (
            <ul className="flex min-w-0 flex-col gap-1.5">
              {sortedParticipants.map((participant) => (
                // `position` is the key, not `id`: the slot is unique, the same person may fill two.
                <li key={participant.position} className="flex min-w-0 flex-col gap-0.5">
                  <span className="text-xs text-muted-foreground">
                    {t('workOrders.form.participantSlotLabel', { n: participant.position })}
                  </span>
                  <RecordPerson user={participant} />
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
