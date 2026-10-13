import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordCanvas, RecordCard } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { ProjectCreateSections } from '@/features/projects/project-create-sections'
import { ProjectFormHeader } from '@/features/projects/project-form-header'
import { useProjectDraftValues } from '@/features/projects/use-project-draft-values'
import { useProjectForm } from '@/features/projects/use-project-form'
import { useProjectFormSubmit } from '@/features/projects/use-project-form-submit'
import type { ProjectDetail, ProjectFormMode } from '@/features/projects/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const PROJECT_FORM_ID = 'project-form'

interface ProjectFormBodyProps {
  /** Duplicate (row action "duplicate"): the project the new one is prefilled from. */
  source?: ProjectDetail
  onSuccess: (project: ProjectDetail) => void
  onCancel: () => void
  /** The sequential code suggestion prefilled into the `code` row (spec 0025). */
  initialCode: string
}

/**
 * The project create form UI — a bare create or a duplicate, both through the
 * create path — as a replica of the project detail (spec 0220, spec 0195 D-8
 * applied to Progetti): the same `RecordCanvas`, the record card with its
 * identity band, KPI strip and sections, every row closed until clicked
 * (`ProjectCreateSections`). There is no edit form: the detail edits a
 * persisted project in place.
 */
export function ProjectFormBody({ source, onSuccess, onCancel, initialCode }: ProjectFormBodyProps) {
  const { t } = useTranslation()
  // Stable per source: a fresh object per render would rebuild the form's defaults.
  const mode = useMemo<ProjectFormMode>(
    () => (source ? { type: 'duplicate', source } : { type: 'create' }),
    [source],
  )
  const { form, customFieldErrorPaths } = useProjectForm({ mode, initialCode })
  const { serverError, onSubmit } = useProjectFormSubmit({ form, mode, customFieldErrorPaths, onSuccess })
  const draft = useDraftInlineEdit(form)
  const values = useProjectDraftValues(form.control)
  const { isSubmitting } = form.formState

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={PROJECT_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordFormBody>
            <RecordCard>
              <ProjectFormHeader
                values={values}
                formId={PROJECT_FORM_ID}
                isSubmitting={isSubmitting}
                submitError={serverError}
                onCancel={onCancel}
              />
              <ProjectCreateSections values={values} form={form} draft={draft} />
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes typing far from the top. */}
            <RecordFormActions
              formId={PROJECT_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('projects.form.save')}
              submittingLabel={t('projects.form.saving')}
              cancel={{ label: t('projects.form.cancel'), onCancel }}
            />
          </RecordFormBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
