import { useRef } from 'react'
import type { RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import type { RichTextEditorHandle } from '@/components/rich-text/rich-text-editor'
import { FormControl, useFormField } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { PlaceholderPicker } from '@/features/email-templates/placeholder-picker'
import { useEmailTemplateVariables } from '@/features/email-templates/variables-api'
import type { EmailTemplateFormValues } from '@/features/email-templates/use-email-template-form'
import { useCaretInsertion } from '@/hooks/use-caret-insertion'

/** The placeholder catalog of the module being edited (the form's own `module`). */
function useFormVariables() {
  const { control } = useFormContext<EmailTemplateFormValues>()
  const module = useWatch({ control, name: 'module' })
  const query = useEmailTemplateVariables(module ?? 'work_orders')
  return { categories: query.data ?? [], isLoading: query.isLoading, isError: query.isError }
}

/**
 * The subject control: the input with the placeholder picker beside it
 * (AC-022), inserting at the last known caret through the shared
 * `useCaretInsertion` hook.
 */
export function EmailTemplateSubjectEditor() {
  const { t } = useTranslation()
  const { control, setValue } = useFormContext<EmailTemplateFormValues>()
  const subject = useWatch({ control, name: 'subject' })
  const inputRef = useRef<HTMLInputElement | null>(null)
  const variables = useFormVariables()
  const { trackSelection, insertAtCaret } = useCaretInsertion(inputRef, subject ?? '', (next) =>
    setValue('subject', next, { shouldDirty: true, shouldValidate: true }),
  )

  return (
    <MetaField control={control} name="subject" metaKey="subject" label={t('emailTemplates.form.subject')}>
      {({ field, disabled, readOnly }) => (
        <div className="flex items-center gap-1.5">
          <FormControl>
            <Input
              autoComplete="off"
              disabled={disabled}
              readOnly={readOnly}
              name={field.name}
              value={field.value}
              onBlur={field.onBlur}
              onChange={field.onChange}
              onSelect={trackSelection}
              ref={(node) => {
                field.ref(node)
                inputRef.current = node
              }}
            />
          </FormControl>
          <PlaceholderPicker {...variables} disabled={disabled} onInsert={insertAtCaret} />
        </div>
      )}
    </MetaField>
  )
}

/**
 * The body editor with its own placeholder picker above it (AC-022), bound to
 * the editor's selection (`RichTextEditorHandle.insertText`), never to a
 * shared "active field". Shared by the detail row and the create form, where
 * it stays open.
 */
export function EmailTemplateBodyEditor() {
  const { t } = useTranslation()
  const { control } = useFormContext<EmailTemplateFormValues>()
  const editorRef = useRef<RichTextEditorHandle>(null)
  const variables = useFormVariables()

  return (
    <div className="flex min-w-0 flex-col gap-2">
      <div className="flex justify-end">
        <PlaceholderPicker {...variables} onInsert={(variable) => editorRef.current?.insertText(variable)} />
      </div>
      <MetaField control={control} name="body" metaKey="body" label={t('emailTemplates.form.body')}>
        {({ field, disabled }) => (
          <BodyFieldControl value={field.value} onChange={field.onChange} disabled={disabled} editorRef={editorRef} />
        )}
      </MetaField>
    </div>
  )
}

interface BodyFieldControlProps {
  value: string | null
  onChange: (html: string | null) => void
  disabled: boolean
  editorRef: RefObject<RichTextEditorHandle | null>
}

/**
 * Bridges `RichTextEditor` into the RHF/`FormItem` context: reads the
 * accessible-error triad ids via `useFormField()` (one level inside the
 * `MetaField` render prop) and forwards the imperative ref the placeholder
 * picker inserts through. Images are disallowed (spec 0175 D-11).
 */
function BodyFieldControl({ value, onChange, disabled, editorRef }: BodyFieldControlProps) {
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()

  return (
    <RichTextEditor
      ref={editorRef}
      id={formItemId}
      value={value}
      onChange={onChange}
      disabled={disabled}
      allowImages={false}
      aria-invalid={Boolean(error)}
      aria-describedby={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
    />
  )
}
