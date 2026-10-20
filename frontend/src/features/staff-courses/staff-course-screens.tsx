/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { staffCourseKeys, fetchStaffCourse } from '@/features/staff-courses/api'
import { StaffCourseForm } from '@/features/staff-courses/staff-course-form'
import { StaffCourseDetailView } from '@/features/staff-courses/staff-course-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { ModuleDetailScreenProps, ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { STAFF_COURSES_DOMAIN, type StaffCourse } from '@/features/staff-courses/types'

/** Content-only `staff-courses` screens for the module registry (spec 0042). */
export function StaffCourseDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: course,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(staffCourseKeys.detail(id), () => fetchStaffCourse(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('staffCourses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !course) {
    return <DetailLoading />
  }

  return <StaffCourseDetailView course={course} onChanged={onChanged} />
}

/** Create only: there is no edit form, and the registry generates no `:id/edit` route. */
export function StaffCourseFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: StaffCourse) => {
    void queryClient.invalidateQueries({ queryKey: staffCourseKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  return <StaffCourseForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: STAFF_COURSES_DOMAIN,
  basePath: `/${STAFF_COURSES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.staffCourses',
  DetailScreen: StaffCourseDetailScreen,
  FormScreen: StaffCourseFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
