import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { ListChecks } from 'lucide-react'
import { DetailEmpty, DetailMonogram } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { Badge } from '@/components/ui/badge'
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
import { JobActivityRows } from '@/features/job-activities/job-activity-rows'
import { useJobActivityForm, type JobActivityFormValues } from '@/features/job-activities/use-job-activity-form'
import { JOB_ACTIVITIES_DOMAIN, type JobActivityDetailWithPermissions, type JobActivityFormMode } from '@/features/job-activities/types'

interface JobActivityDetailViewProps {
  jobActivity: JobActivityDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single activity on the record kit: identity band, the rows
 * (name, description) edited IN PLACE one at a time (each save a PATCH of
 * that field alone), the read-only job roles (linked from the role side), the
 * activity log in the side column and a metadata footer.
 */
export function JobActivityDetailView(props: JobActivityDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.jobActivity.permissions}>
      <JobActivityDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function JobActivityDetailContent({ jobActivity, onChanged }: JobActivityDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<JobActivityFormMode>(() => ({ type: 'edit', jobActivity }), [jobActivity])
  const { form, onSubmit, serverError, clearServerError } = useJobActivityForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<JobActivityFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('jobActivities.form.genericError'),
  })
  const canEdit = jobActivity.permissions.resource.update
  const createdAt = formatDateTime(jobActivity.created_at)
  const updatedAt = formatDateTime(jobActivity.updated_at)
  const tabs: RecordCollaborationTab[] = jobActivity.permissions.actions.view_activity
    ? [activityLogTab(JOB_ACTIVITIES_DOMAIN, jobActivity.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={<RecordCardHeader media={<DetailMonogram name={jobActivity.name} icon={<ListChecks />} />} title={jobActivity.name} />}
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('jobActivities.form.sections.identity.title')} full>
              <RecordFieldList>
                <JobActivityRows inline={inline} canEdit={canEdit} />
                <RecordField label={t('jobActivities.detail.jobRoles')}>
                  {jobActivity.job_roles.length > 0 ? (
                    <div className="flex flex-wrap gap-1.5">
                      {jobActivity.job_roles.map((role) => (
                        <Badge key={role.id} variant="outline" className="max-w-full">
                          <RecordLink domain="job-roles" id={role.id}>
                            {role.name}
                          </RecordLink>
                        </Badge>
                      ))}
                    </div>
                  ) : (
                    <DetailEmpty />
                  )}
                </RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('jobActivities.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('jobActivities.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
