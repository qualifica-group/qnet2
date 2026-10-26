import { useTranslation } from 'react-i18next'
import { MessagesSquare, Paperclip } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'
import { NotesSection } from '@/features/notes/notes-section'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { PersonalDataRecordSections } from '@/features/personal-data/personal-data-record-cards'
import { REGISTRY_ATTACHABLE_ALIAS, registryDetailQueryKey } from '@/features/registries/api'
import { RegistryDetailHeader, RegistryStatsStrip } from '@/features/registries/registry-detail-header'
import { RegistryDetailSections } from '@/features/registries/registry-detail-sections'
import { RegistryGeneralNotesRow } from '@/features/registries/registry-general-notes'
import { RegistryRelatedRecords } from '@/features/registries/registry-related-records'
import { useRegistryInlineEdit } from '@/features/registries/use-registry-inline-edit'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

interface RegistryDetailViewProps {
  registry: RegistryDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the anagrafica (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * The record's collaboration tabs (Notes | Documents | Activity, specs 0312 and
 * 0173), each gated by its OWN authorization source and absent entirely when
 * unauthorized.
 */
function useCollaborationTabs(registry: RegistryDetailWithPermissions): RecordCollaborationTab[] {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const tabs: RecordCollaborationTab[] = []

  // The thread is readable exactly when the record is (RegistryNotable); the server re-authorizes it.
  if (registry.permissions.resource.view) {
    tabs.push({
      value: 'notes',
      label: t('notes.section.title'),
      icon: <MessagesSquare className="size-3.5" aria-hidden="true" />,
      content: <NotesSection entityType="registries" entityId={registry.id} showHeader={false} />,
    })
  }

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
 * Detail of a single anagrafica, rendered as an enterprise-CRM record — the
 * same `RecordBody` every detail uses: identity + KPI, then the general notes
 * (spec 0207), the sections and "how to reach them" (contacts, addresses) in
 * the record card; the documents/activity tabs in the side column; the
 * client's related records in tabs below (spec 0199), a metadata footer.
 *
 * There is no edit page (spec 0200): the sections' fields — custom fields
 * included — edit IN PLACE, one at a time (`RecordInlineField`, driven by
 * `useRegistryInlineEdit`), each save a PATCH of that field alone; contacts
 * and addresses are managed in place by their own sections.
 *
 * Container-query driven (`RecordCanvas`), so the same tree renders correctly
 * on the dedicated `/registries/:id` page and inside the modal Sheet the
 * module registry can open instead (spec 0042).
 */
export function RegistryDetailView(props: RegistryDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.registry.permissions}>
      <RegistryDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function RegistryDetailContent({ registry, onChanged }: RegistryDetailViewProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editor = useRegistryInlineEdit(registry, onChanged)
  const createdAt = formatDateTime(registry.created_at)
  const collaborationTabs = useCollaborationTabs(registry)

  // A contact/address persisted on its own: refetch the record (its header
  // and primary contacts read them too) and tell the host.
  const handleCardChanged = () => {
    void queryClient.invalidateQueries({ queryKey: registryDetailQueryKey(registry.id) })
    onChanged?.()
  }

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <RegistryDetailHeader registry={registry} />
            <RegistryStatsStrip
              referents={registry.referents.length}
              managers={registry.managers.length}
              sectors={registry.sectors.length}
              employees={registry.employee_count}
            />
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...editor.form}>
          {/* Spec 0207: the general notes head the record's sections, edited in place inside their callout. */}
          <div className="px-4 pt-4">
            <RegistryGeneralNotesRow
              notes={registry.general_notes}
              control={editor.form.control}
              inline={editor.inline}
            />
          </div>
          <RegistryDetailSections registry={registry} editor={editor} />
        </Form>
        <RecordSectionsGrid className="border-t">
          <PersonalDataRecordSections
            card={registry.personal_data}
            contactsTitle={t('registries.form.sections.contacts.title')}
            addressesTitle={t('registries.form.sections.addresses.title')}
            showSiteType
            editing={{ fieldPermission: editor.card.fieldPermission, onChanged: handleCardChanged }}
          />
        </RecordSectionsGrid>
      </RecordBody>

      {/* Spec 0199: the client's Opportunita'/Offerte/Commesse/Task, full width below the record. */}
      <RegistryRelatedRecords registryId={registry.id} />

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
