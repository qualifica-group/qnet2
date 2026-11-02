import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { LifeBuoy } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { SupportTopicRows } from '@/features/support-topics/support-topic-rows'
import { useSupportTopicForm } from '@/features/support-topics/use-support-topic-form'
import type { SupportTopicDetail } from '@/features/support-topics/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const SUPPORT_TOPIC_FORM_ID = 'support-topic-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface SupportTopicFormBodyProps {
  onSuccess: (supportTopic: SupportTopicDetail) => void
  onCancel: () => void
}

/** The create form as a replica of the detail: rows closed until clicked, Salva creates everything at once. */
export function SupportTopicFormBody({ onSuccess, onCancel }: SupportTopicFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useSupportTopicForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('supportTopics.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={SUPPORT_TOPIC_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('supportTopics.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<LifeBuoy />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('supportTopics.form.save'),
          saving: t('supportTopics.form.saving'),
          cancel: t('supportTopics.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('supportTopics.form.sectionTitle')} full>
            <RecordFieldList>
              <SupportTopicRows inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
