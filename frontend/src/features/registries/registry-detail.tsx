import { useTranslation } from 'react-i18next'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { PersonalDataReadOnlyCards } from '@/features/personal-data/personal-data-read-only-cards'
import { RegistryDetailHeader, RegistryDetailStats } from '@/features/registries/registry-detail-header'
import { RegistryDetailSections } from '@/features/registries/registry-detail-sections'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

interface RegistryDetailViewProps {
  registry: RegistryDetailWithPermissions
  /** Opens the module's edit surface (page navigation or sheet swap); absent = no edit affordance. */
  onEdit?: () => void
}

/**
 * Read-only detail of a single anagrafica, rendered as an enterprise-CRM
 * record — the same kit the Opportunità record uses (user directive
 * 2026-09-11): identity + KPI + sections on the left, "how to reach them"
 * (contacts, addresses) and the activity log on the right, a metadata footer.
 *
 * Container-query driven (`RecordCanvas`), so the same tree renders correctly
 * on the dedicated `/registries/:id` page and inside the modal Sheet the
 * module registry can open instead (spec 0042).
 *
 * The side column is NOT gated: contacts and addresses are the reason this
 * card is opened most of the time, and they exist for every anagrafica — only
 * the activity block inside it answers to a permission.
 */
export function RegistryDetailView({ registry, onEdit }: RegistryDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(registry.created_at)
  const collaborationTabs: RecordCollaborationTab[] = registry.permissions.actions.view_activity
    ? [activityLogTab('registries', registry.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        side={
          <>
            <PersonalDataReadOnlyCards
              card={registry.personal_data}
              contactsTitle={t('registries.form.sections.contacts.title')}
              addressesTitle={t('registries.form.sections.addresses.title')}
              showSiteType
            />
            <RecordCollaborationCard tabs={collaborationTabs} />
          </>
        }
      >
        <RecordCard>
          <RegistryDetailHeader registry={registry} onEdit={onEdit} />
          <RegistryDetailStats registry={registry} />
          <RegistryDetailSections registry={registry} />
        </RecordCard>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('registries.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
