import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { GraduationCap } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { StaffCourseRows } from '@/features/staff-courses/staff-course-rows'
import { useStaffCourseDraftRoles } from '@/features/staff-courses/use-staff-course-draft-roles'
import { useStaffCourseForm } from '@/features/staff-courses/use-staff-course-form'
import type { StaffCourse } from '@/features/staff-courses/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const FORM_ID = 'staff-course-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface StaffCourseFormBodyProps {
  onSuccess: (course: StaffCourse) => void
  onCancel: () => void
}

/**
 * The course create form as a replica of the detail: the same rows, closed until clicked
 * (nothing saved per row); Save validates and creates everything at once.
 */
export function StaffCourseFormBody({ onSuccess, onCancel }: StaffCourseFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useStaffCourseForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const jobRoles = useStaffCourseDraftRoles(form.control)
  const heading = name.trim() || t('staffCourses.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('staffCourses.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<GraduationCap />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('staffCourses.form.save'),
          saving: t('staffCourses.form.saving'),
          cancel: t('staffCourses.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('staffCourses.form.sections.identity.title')} full>
            <RecordFieldList>
              <StaffCourseRows inline={draft} canEdit jobRoles={jobRoles} />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
