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

  return (
    <RecordCanvas>
      <div className={cn(RECORD_BODY_GRID_CLASS, RECORD_BODY_WITH_SIDE_CLASS)}>
        <div className={RECORD_COLUMN_CLASS}>
          <RecordCard>
            <RegistryDetailHeader registry={registry} onEdit={onEdit} />
            <RegistryDetailStats registry={registry} />
            <RegistryDetailSections registry={registry} />
          </RecordCard>
        </div>

        <div className={RECORD_COLUMN_CLASS}>
          <PersonalDataReadOnlyCards
            card={registry.personal_data}
            contactsTitle={t('registries.form.sections.contacts.title')}
            addressesTitle={t('registries.form.sections.addresses.title')}
            showSiteType
          />

          {registry.permissions.actions.view_activity ? (
            <RecordCard className="p-4">
              <RecordSection title={t('activityLog.title')} icon={<History />}>
                <ActivityLogSection resource="registries" id={registry.id} />
              </RecordSection>
            </RecordCard>
          ) : null}
        </div>
      </div>

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
