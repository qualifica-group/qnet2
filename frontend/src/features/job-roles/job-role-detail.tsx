import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Briefcase } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordField,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { JobRoleRows } from '@/features/job-roles/job-role-rows'
import { useJobRoleForm, type JobRoleFormValues } from '@/features/job-roles/use-job-role-form'
import { JOB_ROLES_DOMAIN, type JobRoleDetailWithPermissions, type JobRoleFormMode } from '@/features/job-roles/types'

interface JobRoleDetailViewProps {
  jobRole: JobRoleDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single job role on the record kit: identity band, the rows
 * (name, description, activities) edited IN PLACE one at a time (each save a
 * PATCH of that field alone), the read-only count of assigned
 * people, the activity log in the side column and a metadata footer.
 */
export function JobRoleDetailView(props: JobRoleDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.jobRole.permissions}>
      <JobRoleDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function JobRoleDetailContent({ jobRole, onChanged }: JobRoleDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<JobRoleFormMode>(() => ({ type: 'edit', jobRole }), [jobRole])
  const { form, onSubmit, serverError, clearServerError } = useJobRoleForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<JobRoleFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('jobRoles.form.genericError'),
  })
  const canEdit = jobRole.permissions.resource.update
  const createdAt = formatDateTime(jobRole.created_at)
  const updatedAt = formatDateTime(jobRole.updated_at)
  const tabs: RecordCollaborationTab[] = jobRole.permissions.actions.view_activity
    ? [activityLogTab(JOB_ROLES_DOMAIN, jobRole.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={<RecordCardHeader media={<DetailMonogram name={jobRole.name} icon={<Briefcase />} />} title={jobRole.name} />}
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('jobRoles.form.sections.identity.title')} full>
              <RecordFieldList>
                <JobRoleRows inline={inline} canEdit={canEdit} jobActivities={jobRole.job_activities} />
                <RecordField label={t('jobRoles.detail.usersCount')}>{jobRole.users_count}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('jobRoles.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('jobRoles.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
