import { useTranslation } from 'react-i18next'
import { Award, ClipboardList, GraduationCap } from 'lucide-react'
import { RecordField, RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { StaffTrainingAttachment } from '@/features/staff-trainings/staff-training-attachment'
import type { StaffTrainingActions } from '@/features/staff-trainings/use-staff-training-actions'
import type { StaffTraining } from '@/features/staff-trainings/types'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'

interface StaffTrainingDetailBodyProps {
  training: StaffTraining
  actions: StaffTrainingActions
  canManage: boolean
}

/**
 * Read-only data of a training in record sections (spec 0287 D-2): dates and validity, who
 * recorded it with the notes, the certificate. Person, course and outcome live in the identity band.
 */
export function StaffTrainingDetailBody({ training, actions, canManage }: StaffTrainingDetailBodyProps) {
  const { t } = useTranslation()
  const prefix = 'staffTrainings.detail'

  return (
    <RecordSectionsGrid>
      <RecordSection title={t(`${prefix}.sections.training`)} icon={<GraduationCap />}>
        <RecordFieldList>
          <RecordField label={t(`${prefix}.completedOn`)}>{formatDate(training.completed_on)}</RecordField>
          <RecordField label={t(`${prefix}.expiresOn`)}>
            {training.expires_on ? formatDate(training.expires_on) : t(`${prefix}.neverExpires`)}
          </RecordField>
          <RecordField label={t(`${prefix}.validity`)}>
            {training.course.validity_months === null
              ? t(`${prefix}.neverExpires`)
              : t(`${prefix}.validityMonths`, { count: training.course.validity_months })}
          </RecordField>
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t(`${prefix}.sections.record`)} icon={<ClipboardList />}>
        <RecordFieldList>
          <RecordField label={t(`${prefix}.recordedBy`)}>
            {training.recorded_by.name} ({formatDateTime(training.created_at)})
          </RecordField>
          {training.notes ? (
            <RecordField label={t(`${prefix}.notes`)}>
              <span className="whitespace-pre-wrap">{training.notes}</span>
            </RecordField>
          ) : null}
        </RecordFieldList>
      </RecordSection>
      <RecordSection title={t(`${prefix}.sections.certificate`)} icon={<Award />} full>
        <StaffTrainingAttachment training={training} actions={actions} canManage={canManage} />
      </RecordSection>
    </RecordSectionsGrid>
  )
}
