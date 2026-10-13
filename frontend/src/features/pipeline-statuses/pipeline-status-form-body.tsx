import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Flag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { PipelineStatusRows } from '@/features/pipeline-statuses/pipeline-status-rows'
import { usePipelineStatusForm } from '@/features/pipeline-statuses/use-pipeline-status-form'
import type { PipelineStatusDetail } from '@/features/pipeline-statuses/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const PIPELINE_STATUS_FORM_ID = 'pipeline-status-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface PipelineStatusFormBodyProps {
  onSuccess: (pipelineStatus: PipelineStatusDetail) => void
  onCancel: () => void
}

/**
 * The project status create form as a replica of the detail (spec 0235): the
 * same rows, closed until clicked (Fatto/Ripristina, nothing saved per row);
 * Salva validates and creates everything at once.
 */
export function PipelineStatusFormBody({ onSuccess, onCancel }: PipelineStatusFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = usePipelineStatusForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const customFieldValues = useWatch({ control: form.control, name: 'custom_fields' })
  const heading = name.trim() || t('pipelineStatuses.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={PIPELINE_STATUS_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('pipelineStatuses.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Flag />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('pipelineStatuses.form.save'),
          saving: t('pipelineStatuses.form.saving'),
          cancel: t('pipelineStatuses.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('pipelineStatuses.form.sections.identity.title')} full>
            <RecordFieldList>
              <PipelineStatusRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
          <CustomFieldRecordSections
            resource="pipeline-statuses"
            values={customFieldValues}
            inline={draft}
            control={form.control}
          />
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
