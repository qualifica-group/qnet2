import { useTranslation } from 'react-i18next'
import { History } from 'lucide-react'
import {
  RecordCanvas,
  RecordCard,
  RecordMeta,
  RecordSection,
} from '@/components/detail/record-panel'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { cn } from '@/lib/utils'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { PersonalDataReadOnlyCards } from '@/features/personal-data/personal-data-read-only-cards'
import { ReferentDetailHeader, ReferentDetailStats } from '@/features/referents/referent-detail-header'
import { ReferentDetailSections } from '@/features/referents/referent-detail-sections'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

interface ReferentDetailViewProps {
  referent: ReferentDetailWithPermissions
  /** Opens the module's edit surface (page navigation or sheet swap); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single referente, rendered as an enterprise-CRM record
 * — the same kit the Opportunità record and the anagrafica use (user directive
 * 2026-09-11): identity + KPI + sections on the left, "how to reach them"
 * (contacts, addresses) and the activity log on the right, a metadata footer.
 *
 * Deliberately the TWIN of `RegistryDetailView`, down to the shared blocks
 * (`PersonalDataReadOnlyCards`, `PersonalDataIdentityRows`): a referente is an
 * anagraphic card with fewer own fields, and the two screens must not drift on
 * the half they have in common.
 */
export function ReferentDetailView({ referent, onEdit }: ReferentDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(referent.created_at)

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <ReferentDetailHeader referent={referent} onEdit={onEdit} />
            <ReferentDetailStats referent={referent} />
            <ReferentDetailSections referent={referent} />
          </RecordCard>
        </div>

        <div className={RECORD_COLUMN_CLASS}>
          <PersonalDataReadOnlyCards
            card={referent.personal_data}
            contactsTitle={t('referents.form.sections.contacts.title')}
            addressesTitle={t('referents.form.sections.addresses.title')}
          />

          {referent.permissions.actions.view_activity ? (
            <RecordCard className="p-4">
              <RecordSection title={t('activityLog.title')} icon={<History />}>
                <ActivityLogSection resource="referents" id={referent.id} />
              </RecordSection>
            </RecordCard>
          ) : null}
        </div>
      </div>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('referents.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
