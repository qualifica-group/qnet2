import { useTranslation } from 'react-i18next'
import { Loader2, Puzzle, TriangleAlert } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCanvas, RecordCard, RecordCardHeader, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordFormActions } from '@/components/record-form/record-form-actions'
import { RecordFormBody } from '@/components/record-form/record-form-body'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { CustomFieldDefinitionSections } from '@/features/custom-fields/custom-field-definition-sections'
import { useCustomFieldDefinitionForm } from '@/features/custom-fields/use-custom-field-definition-form'
import type { CustomFieldDefinitionDetail } from '@/features/custom-fields/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const CUSTOM_FIELD_FORM_ID = 'custom-field-definition-form'

interface CustomFieldDefinitionFormBodyProps {
  onSuccess: (definition: CustomFieldDefinitionDetail) => void
  onCancel: () => void
}

/**
 * The custom field definition create form as a replica of the detail (spec
 * 0231): the record card with its identity band (monogram and title follow
 * the label being typed, Annulla/Salva), the rows closed until clicked
 * (Fatto/Ripristina, nothing saved per row) and the type-dependent editors
 * OPEN, since they are editors rather than fields. All non-render logic lives
 * in `useCustomFieldDefinitionForm`.
 */
export function CustomFieldDefinitionFormBody({ onSuccess, onCancel }: CustomFieldDefinitionFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useCustomFieldDefinitionForm({ onSuccess })
  const draft = useDraftInlineEdit(form)
  const { isSubmitting } = form.formState
  const heading = form.watch('label').trim() || t('customFields.form.createTitle')

  return (
    <Form {...form}>
      {/* `display: contents`: this native `<form>` only scopes the HTML submit
          boundary, it must not become an extra box around the canvas. */}
      <form id={CUSTOM_FIELD_FORM_ID} onSubmit={form.handleSubmit(onSubmit)} className="contents" noValidate>
        <RecordCanvas>
          <RecordFormBody>
            <RecordCard>
              <RecordCardHeader
                media={<DetailMonogram name={heading} icon={<Puzzle />} />}
                title={heading}
                subtitle={t('customFields.form.createSubtitle')}
                actions={
                  <>
                    <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
                      {t('customFields.form.cancel')}
                    </Button>
                    <Button type="submit" form={CUSTOM_FIELD_FORM_ID} disabled={isSubmitting}>
                      {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
                      {isSubmitting ? t('customFields.form.saving') : t('customFields.form.save')}
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
                <CustomFieldDefinitionSections form={form} inline={draft} creating canEdit />
              </RecordSectionsGrid>
            </RecordCard>

            {/* The same actions the identity band carries, repeated where the
                form ends: the operator finishes the editors far from the top. */}
            <RecordFormActions
              formId={CUSTOM_FIELD_FORM_ID}
              isSubmitting={isSubmitting}
              submitLabel={t('customFields.form.save')}
              submittingLabel={t('customFields.form.saving')}
              cancel={{ label: t('customFields.form.cancel'), onCancel }}
            />
          </RecordFormBody>
        </RecordCanvas>
      </form>
    </Form>
  )
}
