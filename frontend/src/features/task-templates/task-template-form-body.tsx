import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { ListChecks, Loader2, TriangleAlert } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCard, RecordCardHeader, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { useResourcePermissions } from '@/features/authorization/permissions'
import {
  TaskTemplateBoardEditor,
  TaskTemplateBoardSection,
  TaskTemplateIdentityRecordSection,
} from '@/features/task-templates/task-template-record-sections'
import { useTaskTemplateForm } from '@/features/task-templates/use-task-template-form'
import type { TaskTemplateDetail } from '@/features/task-templates/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const TASK_TEMPLATE_FORM_ID = 'task-template-form'

interface TaskTemplateFormBodyProps {
  onSuccess: (taskTemplate: TaskTemplateDetail) => void
  onCancel: () => void
}

/**
 * The task template create form UI as a replica of the detail (spec 0227
 * D-4): the record card with its identity band (monogram and title follow
 * the name being typed, Annulla/Salva), the "Dettagli" rows closed until
 * clicked (Fatto/Ripristina, nothing saved per row) and the "Fasi e
 * attivita'" board OPEN, since it is an editor rather than a field (the
 * precedent of the quote's line grids, spec 0197). `items` stays a custom
 * authorization field (D-1): hidden when not visible, disabled when not
 * editable. All non-render logic lives in `useTaskTemplateForm`.
 */
export function TaskTemplateFormBody({ onSuccess, onCancel }: TaskTemplateFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const taskTemplateForm = useTaskTemplateForm({ onSuccess })
  const { form, serverError, onSubmit } = taskTemplateForm
  const draft = useDraftInlineEdit(form)
  const values = useWatch({ control: form.control })
  const { isSubmitting } = form.formState
  const itemsPermission = fieldPermission('items')
  const heading = values.name?.trim() || t('taskTemplates.form.createTitle')

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={TASK_TEMPLATE_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordFormBody>
            <RecordCard>
              <RecordCardHeader
                media={<DetailMonogram name={heading} icon={<ListChecks />} />}
                title={heading}
                subtitle={t('taskTemplates.form.createSubtitle')}
                actions={
                  <>
                    <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
                      {t('taskTemplates.form.cancel')}
                    </Button>
                    <Button type="submit" form={TASK_TEMPLATE_FORM_ID} disabled={isSubmitting}>
                      {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                      {isSubmitting ? t('taskTemplates.form.saving') : t('taskTemplates.form.save')}
                    </Button>
                  </>
                }
              />
              {serverError ? (
                <div
                  role="alert"
                  className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
                >
                  <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                  {serverError}
                </div>
              ) : null}
              <RecordSectionsGrid>
                <TaskTemplateIdentityRecordSection
                  values={{
                    name: values.name ?? '',
                    description: values.description ?? null,
                    is_active: values.is_active ?? true,
                  }}
                  form={form}
                  inline={draft}
                  canEdit
                />
                {itemsPermission.visible ? (
                  <TaskTemplateBoardSection>
                    <TaskTemplateBoardEditor
                      board={taskTemplateForm}
                      disabled={!itemsPermission.editable || itemsPermission.disabled || isSubmitting}
                    />
                  </TaskTemplateBoardSection>
                ) : null}
              </RecordSectionsGrid>
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes the board far from the top. */}
            <RecordFormActions
              formId={TASK_TEMPLATE_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('taskTemplates.form.save')}
              submittingLabel={t('taskTemplates.form.saving')}
              cancel={{ label: t('taskTemplates.form.cancel'), onCancel }}
            />
          </RecordFormBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
