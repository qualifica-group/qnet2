import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { StaffCourseFormBody } from '@/features/staff-courses/staff-course-form-body'
import { STAFF_COURSES_DOMAIN, type StaffCourse } from '@/features/staff-courses/types'

interface StaffCourseFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (course: StaffCourse) => void
  onCancel: () => void
}

/**
 * The course create form (no edit form: the detail edits in place). Resolves the
 * create-context `ResourcePermissions` (`GET /meta/staff-courses`) before rendering the body.
 */
export function StaffCourseForm(props: StaffCourseFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta(STAFF_COURSES_DOMAIN)

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <StaffCourseFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
