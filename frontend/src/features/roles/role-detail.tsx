import { useTranslation } from 'react-i18next'
import { ShieldCheck } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordCardHeader, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { PermissionsEditor } from '@/features/roles/permission-explorer/permissions-editor'
import { RolePermissionsOverview } from '@/features/roles/role-overview'
import { RoleIdentityRecordSection, RolePermissionsRecordSection } from '@/features/roles/role-record-sections'
import { ROLE_PERMISSIONS_FIELD, useRoleInlineEdit } from '@/features/roles/use-role-inline-edit'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { RoleDetailWithPermissions } from '@/features/roles/types'

interface RoleDetailProps {
  role: RoleDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the role (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single role on the record kit every detail uses: the identity
 * band, the "Dettagli ruolo" rows, the "Permessi" block, the custom fields,
 * the activity in the side column and a metadata footer.
 *
 * There is no edit page (spec 0230): every row edits IN PLACE, one at a time
 * (`useRoleInlineEdit`). The permissions are ONE full-width row whose pencil
 * swaps the granted-abilities overview for the very explorer the create form
 * uses, saving permissions and field permissions alone.
 */
export function RoleDetailView(props: RoleDetailProps) {
  // The rows read the field permissions while they are built, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.role.authorization}>
      <RoleDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function RoleDetailContent({ role, onChanged }: RoleDetailProps) {
  const { t } = useTranslation()
  const { form, inline, canManageFieldPermissions } = useRoleInlineEdit(role, onChanged)
  const canEdit = role.authorization.resource.update
  const createdAt = formatDateTime(role.created_at)
  const tabs: RecordCollaborationTab[] = role.authorization.actions.view_activity
    ? [activityLogTab('roles', role.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={
          <RecordCardHeader
            media={<DetailMonogram name={role.name} icon={<ShieldCheck />} />}
            title={role.name}
            legacyId={role.old_id}
          />
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RoleIdentityRecordSection
              values={{ name: role.name, users: role.users ?? [] }}
              form={form}
              inline={inline}
              canEdit={canEdit}
            />
            <RolePermissionsRecordSection count={role.permissions.length}>
              <RecordInlineField
                field={ROLE_PERMISSIONS_FIELD}
                label={t('roles.form.permissions')}
                layout="block"
                inline={inline}
                canEdit={canEdit}
                editor={
                  <PermissionsEditor control={form.control} canManageFieldPermissions={canManageFieldPermissions} />
                }
              >
                <RolePermissionsOverview granted={role.permissions} />
              </RecordInlineField>
            </RolePermissionsRecordSection>
            <CustomFieldRecordSections
              resource="roles"
              values={role.custom_fields ?? {}}
              inline={inline}
              control={form.control}
            />
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('roles.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
