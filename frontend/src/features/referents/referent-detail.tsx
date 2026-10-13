import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { PersonalDataRecordSections } from '@/features/personal-data/personal-data-record-cards'
import { referentDetailQueryKey } from '@/features/referents/api'
import { ReferentDetailHeader, ReferentStatsStrip } from '@/features/referents/referent-detail-header'
import { ReferentDetailSections } from '@/features/referents/referent-detail-sections'
import { useReferentInlineEdit } from '@/features/referents/use-referent-inline-edit'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

interface ReferentDetailViewProps {
  referent: ReferentDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the referente (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single referente, rendered as an enterprise-CRM record — the
 * same `RecordBody` every detail uses: identity + KPI, then the sections and
 * "how to reach them" (contacts, addresses) in the record card, the activity
 * log in the side column, a metadata footer.
 *
 * There is no edit page (spec 0216): the sections' fields — custom fields
 * included — edit IN PLACE, one at a time (`RecordInlineField`, driven by
 * `useReferentInlineEdit`), each save a PATCH of that field alone; contacts
 * and addresses are managed in place by their own sections.
 *
 * Deliberately the TWIN of `RegistryDetailView`, down to the shared blocks
 * (`PersonalDataIdentityRecordSection`, `PersonalDataRecordSections`): a
 * referente is an anagraphic card with fewer own fields, and the two screens
 * must not drift on the half they have in common.
 */
export function ReferentDetailView(props: ReferentDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.referent.permissions}>
      <ReferentDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function ReferentDetailContent({ referent, onChanged }: ReferentDetailViewProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editor = useReferentInlineEdit(referent, onChanged)
  const createdAt = formatDateTime(referent.created_at)
  const card = referent.personal_data
  const collaborationTabs: RecordCollaborationTab[] = referent.permissions.actions.view_activity
    ? [activityLogTab('referents', referent.id, t('activityLog.title'))]
    : []

  // A contact/address persisted on its own: refetch the record (its KPI strip
  // counts them) and tell the host.
  const handleCardChanged = () => {
    void queryClient.invalidateQueries({ queryKey: referentDetailQueryKey(referent.id) })
    onChanged?.()
  }

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <ReferentDetailHeader referent={referent} />
            <ReferentStatsStrip
              contacts={card?.contacts.length ?? 0}
              addresses={card?.addresses.length ?? 0}
              cardType={card?.type ?? null}
            />
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...editor.form}>
          <ReferentDetailSections referent={referent} editor={editor} />
        </Form>
        <RecordSectionsGrid className="border-t">
          <PersonalDataRecordSections
            card={card}
            contactsTitle={t('referents.form.sections.contacts.title')}
            addressesTitle={t('referents.form.sections.addresses.title')}
            editing={{ fieldPermission: editor.card.fieldPermission, onChanged: handleCardChanged }}
          />
        </RecordSectionsGrid>
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
