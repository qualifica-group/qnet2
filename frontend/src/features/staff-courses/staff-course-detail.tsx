import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { GraduationCap } from 'lucide-react'
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
import { StaffCourseRows } from '@/features/staff-courses/staff-course-rows'
import { useStaffCourseForm, type StaffCourseFormValues } from '@/features/staff-courses/use-staff-course-form'
import { STAFF_COURSES_DOMAIN, type StaffCourseDetail, type StaffCourseFormMode } from '@/features/staff-courses/types'

interface StaffCourseDetailViewProps {
  course: StaffCourseDetail
  /** Called after an in-place save, so the host refreshes whatever lists the record. */
  onChanged?: () => void
}

/**
 * Detail of a single course on the record kit: identity band, the rows edited IN PLACE one at a
 * time (each save a PATCH of that field alone), the read-only count of trainings, the activity log
 * and a metadata footer.
 */
export function StaffCourseDetailView(props: StaffCourseDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.course.permissions}>
      <StaffCourseDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function StaffCourseDetailContent({ course, onChanged }: StaffCourseDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<StaffCourseFormMode>(() => ({ type: 'edit', course }), [course])
  const { form, onSubmit, serverError, clearServerError } = useStaffCourseForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<StaffCourseFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('staffCourses.form.genericError'),
  })
  const canEdit = course.permissions.resource.update
  const createdAt = formatDateTime(course.created_at)
  const updatedAt = formatDateTime(course.updated_at)
  const tabs: RecordCollaborationTab[] = course.permissions.actions.view_activity
    ? [activityLogTab(STAFF_COURSES_DOMAIN, course.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={tabs}
        header={<RecordCardHeader media={<DetailMonogram name={course.name} icon={<GraduationCap />} />} title={course.name} />}
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('staffCourses.form.sections.identity.title')} full>
              <RecordFieldList>
                <StaffCourseRows inline={inline} canEdit={canEdit} jobRoles={course.job_roles} />
                <RecordField label={t('staffCourses.detail.trainingsCount')}>{course.trainings_count}</RecordField>
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      {createdAt || updatedAt ? (
        <RecordMeta>
          {createdAt ? (
            <span>
              <span className="font-medium">{t('staffCourses.detail.created_at')}</span>{' '}
              <span aria-hidden="true">·</span> {createdAt}
            </span>
          ) : null}
          {updatedAt ? (
            <span>
              <span className="font-medium">{t('staffCourses.detail.updated_at')}</span>{' '}
              <span aria-hidden="true">·</span> {updatedAt}
            </span>
          ) : null}
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
