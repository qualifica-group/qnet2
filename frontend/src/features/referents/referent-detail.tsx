import { useTranslation } from 'react-i18next'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
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
  const collaborationTabs: RecordCollaborationTab[] = referent.permissions.actions.view_activity
    ? [activityLogTab('referents', referent.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        side={
          <>
            <PersonalDataReadOnlyCards
              card={referent.personal_data}
              contactsTitle={t('referents.form.sections.contacts.title')}
              addressesTitle={t('referents.form.sections.addresses.title')}
            />
            <RecordCollaborationCard tabs={collaborationTabs} />
          </>
        }
      >
        <RecordCard>
          <ReferentDetailHeader referent={referent} onEdit={onEdit} />
          <ReferentDetailStats referent={referent} />
          <ReferentDetailSections referent={referent} />
        </RecordCard>
      </RecordBody>

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
