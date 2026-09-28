import { useTranslation } from 'react-i18next'
import { Paperclip } from 'lucide-react'
import { RecordCanvas, RecordCard, RecordMeta } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import {
  RecordCollaborationCard,
  type RecordCollaborationTab,
} from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { PersonalDataReadOnlyCards } from '@/features/personal-data/personal-data-read-only-cards'
import { REGISTRY_ATTACHABLE_ALIAS } from '@/features/registries/api'
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
 * The record's collaboration tabs (Documents | Activity, spec 0173), each gated
 * by its OWN authorization source and absent entirely when unauthorized.
 */
function useCollaborationTabs(registry: RegistryDetailWithPermissions): RecordCollaborationTab[] {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const tabs: RecordCollaborationTab[] = []

  if (registry.permissions.actions.view_documents) {
    tabs.push({
      value: 'documents',
      label: t('attachments.title'),
      icon: <Paperclip className="size-3.5" aria-hidden="true" />,
      content: (
        <DocumentsSection
          resource={REGISTRY_ATTACHABLE_ALIAS}
          id={registry.id}
          canUpload={can('attachments.create')}
          canDelete={can('attachments.delete')}
        />
      ),
    })
  }

  if (registry.permissions.actions.view_activity) {
    tabs.push(activityLogTab('registries', registry.id, t('activityLog.title')))
  }

  return tabs
}

/**
 * Read-only detail of a single anagrafica, rendered as an enterprise-CRM
 * record — the same kit the Opportunità record uses (user directive
 * 2026-09-11): identity + KPI + sections on the left, "how to reach them"
 * (contacts, addresses) and the documents/activity tabs on the right, a
 * metadata footer.
 *
 * Container-query driven (`RecordCanvas`), so the same tree renders correctly
 * on the dedicated `/registries/:id` page and inside the modal Sheet the
 * module registry can open instead (spec 0042).
 *
 * The side column is NOT gated: contacts and addresses are the reason this
 * card is opened most of the time, and they exist for every anagrafica — only
 * the documents/activity tabs inside it answer to a permission.
 */
export function RegistryDetailView({ registry, onEdit }: RegistryDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(registry.created_at)
  const collaborationTabs = useCollaborationTabs(registry)

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
