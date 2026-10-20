import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { StaffCoursesTable } from '@/features/staff-courses/staff-courses-table'

/**
 * Staff courses catalogue page (spec 0284). Light composition only: gates access with
 * `staff-courses.view` and mounts the thin adapter over the generic table.
 */
export default function StaffCoursesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="staff-courses.view"
      fallback={<p className="text-sm text-muted-foreground">{t('staffCourses.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <StaffCoursesTable />
      </div>
    </Can>
  )
}
