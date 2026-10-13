import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField } from '@/components/detail/record-panel'
import { SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import {
  EmailTemplateBodyEditor,
  EmailTemplateSubjectEditor,
} from '@/features/email-templates/email-template-editors'
import { EMAIL_TEMPLATE_MODULES } from '@/features/email-templates/types'
import type { EmailTemplateFormValues } from '@/features/email-templates/use-email-template-form'

interface EmailTemplateRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** `module` is immutable after create: a plain, non-editable row on the detail. */
  moduleLocked: boolean
}

/** The template's header rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function EmailTemplateRows({ inline, canEdit, moduleLocked }: EmailTemplateRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<EmailTemplateFormValues>()
  const module = useWatch({ control, name: 'module' })
  const subject = useWatch({ control, name: 'subject' })
  const moduleLabel = t(`emailTemplates.modules.${module}`)

  return (
    <>
      <TextInlineRow<EmailTemplateFormValues>
        name="name"
        label={t('emailTemplates.form.name')}
        inline={inline}
        canEdit={canEdit}
      />
      {moduleLocked ? (
        <RecordField label={t('emailTemplates.detail.module')}>{moduleLabel}</RecordField>
      ) : (
        <RecordInlineField
          field="module"
          label={t('emailTemplates.form.module')}
          inline={inline}
          editor={
            <MetaField control={control} name="module" metaKey="module" label={t('emailTemplates.form.module')}>
              {({ field, disabled }) => (
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {EMAIL_TEMPLATE_MODULES.map((value) => (
                      <SelectItem key={value} value={value}>
                        {t(`emailTemplates.modules.${value}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </MetaField>
          }
        >
          {moduleLabel}
        </RecordInlineField>
      )}
      <RecordInlineField
        field="subject"
        label={t('emailTemplates.form.subject')}
        inline={inline}
        canEdit={canEdit}
        editor={<EmailTemplateSubjectEditor />}
      >
        {subject ? <span className="break-words">{subject}</span> : <DetailEmpty />}
      </RecordInlineField>
      <TextInlineRow<EmailTemplateFormValues>
        name="description"
        label={t('emailTemplates.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <SwitchInlineRow<EmailTemplateFormValues>
        name="is_active"
        label={t('emailTemplates.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}

interface EmailTemplateBodyRowProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The detail's body: one full-width row whose pencil opens the same editor as creation. */
export function EmailTemplateBodyRow({ inline, canEdit }: EmailTemplateBodyRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<EmailTemplateFormValues>()
  const body = useWatch({ control, name: 'body' })

  return (
    <RecordInlineField
      field="body"
      label={t('emailTemplates.form.body')}
      layout="block"
      inline={inline}
      canEdit={canEdit}
      editor={<EmailTemplateBodyEditor />}
    >
      {body ? <RichTextContent html={body} /> : <DetailEmpty />}
    </RecordInlineField>
  )
}
