import { useTranslation } from 'react-i18next'
import { History, Info, MapPin, Phone } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { enumLabelOf } from '@/features/config/enum-label'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { cardToDraft } from '@/features/personal-data/drafts'
import type { PersonalDataFieldPermissionResolver } from '@/features/personal-data/types'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

/**
 * Renders the (owner-agnostic, reused unchanged) contacts/addresses managers
 * in pure read-only mode: visible, never editable — no add/edit/remove
 * affordance shows up.
 */
const READ_ONLY_FIELD_PERMISSION: PersonalDataFieldPermissionResolver = () => ({
  visible: true,
  editable: false,
  required: false,
  disabled: false,
  readonly: true,
})

/** No-op change handler: the read-only managers never call it. */
function noopChange(): void {}

interface ReferentDetailViewProps {
  referent: ReferentDetailWithPermissions
}

/**
 * Read-only detail of a single referent, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered by the dedicated detail page. Contacts/addresses reuse the same managers as the
 * form (in read-only mode) so the card content never diverges from what the
 * edit form would show (spec 0016).
 */
export function ReferentDetailView({ referent }: ReferentDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(referent.created_at)
  const draft = referent.personal_data ? cardToDraft(referent.personal_data) : null

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={referent.name} />}
        title={referent.name}
        badges={
          <Badge variant="secondary">
            {enumLabelOf('referent_contact_scope', referent.contact_scope)}
          </Badge>
        }
      />

      <DetailSection title={t('referents.detail.details')} icon={<Info />}>
        <DetailGrid>
          <DetailField label={t('referents.form.referentType')}>
            {referent.referent_type?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('referents.form.linkedUser')}>
            {referent.user?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('referents.form.notes')} full>
            {referent.notes || <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('referents.form.sections.contacts.title')} icon={<Phone />}>
        {draft ? (
          <ContactsManager
            value={draft.contacts}
            onChange={noopChange}
            fieldPermission={READ_ONLY_FIELD_PERMISSION}
            showHeader={false}
          />
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      <DetailSection title={t('referents.form.sections.addresses.title')} icon={<MapPin />}>
        {draft ? (
          <AddressesManager
            value={draft.addresses}
            onChange={noopChange}
            fieldPermission={READ_ONLY_FIELD_PERMISSION}
            showHeader={false}
          />
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      {referent.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="referents" id={referent.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('referents.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
