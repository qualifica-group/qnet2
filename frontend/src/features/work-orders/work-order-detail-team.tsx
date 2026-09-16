import { useTranslation } from 'react-i18next'
import { Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RECORD_PERSON_ROW_CLASS, RecordPerson } from '@/components/detail/record-person'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import type { WorkOrderParticipant, WorkOrderSupervisor } from '@/features/work-orders/types'

interface WorkOrderDetailTeamProps {
  supervisors: WorkOrderSupervisor[]
  participants: WorkOrderParticipant[]
}

/**
 * The commessa's Team block, rendered with the SAME `RecordPerson` the
 * Opportunita'/Offerta/Anagrafica records use (avatar + hover card, a click
 * opens the shared user modal), so the people cannot look or behave
 * differently between the screens.
 *
 * Responsabili are an unordered set, stacked in one row. Partecipanti are
 * ordered slots: one row per slot, labelled "Partecipante n" exactly like the
 * form's `ManagerSlotsField`, mirroring the G.A. rows of the other records.
 */
export function WorkOrderDetailTeam({ supervisors, participants }: WorkOrderDetailTeamProps) {
  const { t } = useTranslation()
  const sortedParticipants = [...participants].sort((a, b) => a.position - b.position)

  return (
    <RecordSection title={t('workOrders.detail.sections.team')} icon={<Users />}>
      <RecordFieldList>
        <RecordField label={t('workOrders.detail.supervisors')} className={RECORD_PERSON_ROW_CLASS}>
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
        </RecordField>
        {sortedParticipants.length > 0 ? (
          sortedParticipants.map((participant) => (
            // `position` is the key, not `id`: the slot is unique, the same
            // person may fill two of them.
            <RecordField
              key={participant.position}
              label={t('workOrders.form.participantSlotLabel', { n: participant.position })}
              className={RECORD_PERSON_ROW_CLASS}
            >
              <RecordPerson user={participant} />
            </RecordField>
          ))
        ) : (
          <RecordField label={t('workOrders.detail.participants')}>
            <DetailEmpty />
          </RecordField>
        )}
      </RecordFieldList>
    </RecordSection>
  )
}
