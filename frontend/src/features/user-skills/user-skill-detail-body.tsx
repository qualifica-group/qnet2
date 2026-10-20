import { useTranslation } from 'react-i18next'
import { Gauge } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import type { UserSkill } from '@/features/user-skills/types'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'

/**
 * Read-only data of a person skill in a record section (spec 0287 D-2): category, assessment day,
 * who assessed it and the notes. Person, skill and level live in the identity band.
 */
export function UserSkillDetailBody({ userSkill }: { userSkill: UserSkill }) {
  const { t } = useTranslation()
  const prefix = 'userSkills.detail'

  return (
    <RecordSectionsGrid>
      <RecordSection title={t(`${prefix}.sections.assessment`)} icon={<Gauge />} full>
        <RecordFieldList>
          <RecordField label={t(`${prefix}.category`)}>{userSkill.skill.category ?? <DetailEmpty />}</RecordField>
          <RecordField label={t(`${prefix}.assessedOn`)}>{formatDate(userSkill.assessed_on)}</RecordField>
          <RecordField label={t(`${prefix}.assessedBy`)}>
            {userSkill.assessed_by.name} ({formatDateTime(userSkill.updated_at)})
          </RecordField>
          {userSkill.notes ? (
            <RecordField label={t(`${prefix}.notes`)}>
              <span className="whitespace-pre-wrap">{userSkill.notes}</span>
            </RecordField>
          ) : null}
        </RecordFieldList>
      </RecordSection>
    </RecordSectionsGrid>
  )
}
