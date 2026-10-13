import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Mail } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import { MetaFieldRowContext } from '@/features/authorization/meta-field-row-context'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { EmailTemplateBodyEditor } from '@/features/email-templates/email-template-editors'
import { EmailTemplateRows } from '@/features/email-templates/email-template-rows'
import { useEmailTemplateForm } from '@/features/email-templates/use-email-template-form'
import type { EmailTemplate } from '@/features/email-templates/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const EMAIL_TEMPLATE_FORM_ID = 'email-template-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface EmailTemplateFormBodyProps {
  onSuccess: (emailTemplate: EmailTemplate) => void
  onCancel: () => void
}

/**
 * The email template create form as a replica of the detail (wave 3 of the
 * "no edit pages" plan): the header rows closed until clicked
 * (Fatto/Ripristina, nothing saved per row) and the body editor OPEN, since it
 * is an editor rather than a field (precedent: the quote's line grids, spec
 * 0197); Salva validates and creates everything at once. `body` stays a
 * field-permission-gated field: hidden when not visible, disabled when not
 * editable (spec 0004).
 */
export function EmailTemplateFormBody({ onSuccess, onCancel }: EmailTemplateFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useEmailTemplateForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('emailTemplates.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={EMAIL_TEMPLATE_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('emailTemplates.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Mail />} />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('emailTemplates.form.save'),
          saving: t('emailTemplates.form.saving'),
          cancel: t('emailTemplates.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('emailTemplates.form.sections.identity.title')} full>
            <RecordFieldList>
              <EmailTemplateRows inline={draft} canEdit moduleLocked={false} />
            </RecordFieldList>
          </RecordSection>
          {fieldPermission('body').visible ? (
            <RecordSection title={t('emailTemplates.detail.body')} full>
              {/* The section title already names the editor: its own label stays for assistive tech. */}
              <MetaFieldRowContext.Provider value="body">
                <EmailTemplateBodyEditor />
              </MetaFieldRowContext.Provider>
            </RecordSection>
          ) : null}
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
