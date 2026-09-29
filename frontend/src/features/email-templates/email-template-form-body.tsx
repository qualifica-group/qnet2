import { useRef } from 'react'
import type { RefObject } from 'react'
import { FileText, Mail } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Form, FormControl, useFormField } from '@/components/ui/form'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import type { RichTextEditorHandle } from '@/components/rich-text/rich-text-editor'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useCaretInsertion } from '@/hooks/use-caret-insertion'
import { PlaceholderPicker } from '@/features/email-templates/placeholder-picker'
import { useEmailTemplateVariables } from '@/features/email-templates/variables-api'
import { useEmailTemplateForm } from '@/features/email-templates/use-email-template-form'
import { EMAIL_TEMPLATE_MODULES } from '@/features/email-templates/types'
import type { EmailTemplate, EmailTemplateFormMode } from '@/features/email-templates/types'

interface EmailTemplateFormBodyProps {
  mode: EmailTemplateFormMode
  onSuccess: (emailTemplate: EmailTemplate) => void
  onCancel: () => void
}

/** The metadata keys the identity section owns, in render order. */
const IDENTITY_FIELD_KEYS = ['name', 'module', 'subject', 'description', 'is_active'] as const

/**
 * The email template create/edit form UI. Every field is wrapped in
 * `MetaField` (spec 0004): hidden means absent, non-editable means disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. `module` reuses the immutable-after-create
 * `Select` pattern of `document-layouts`. The placeholder picker (AC-022)
 * appears twice — next to the subject input (caret insertion via the shared
 * `useCaretInsertion` hook) and above the body editor (`RichTextEditorHandle
 * .insertText`) — each bound to its own target, never a shared "active
 * field" tracker. All non-render logic lives in `useEmailTemplateForm`.
 */
export function EmailTemplateFormBody({ mode, onSuccess, onCancel }: EmailTemplateFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useEmailTemplateForm({ mode, onSuccess })

  const subjectInputRef = useRef<HTMLInputElement | null>(null)
  const bodyEditorRef = useRef<RichTextEditorHandle>(null)

  const moduleValue = form.watch('module')
  const subjectValue = form.watch('subject')
  const { trackSelection: trackSubjectSelection, insertAtCaret: insertIntoSubject } = useCaretInsertion(
    subjectInputRef,
    subjectValue ?? '',
    (next) => form.setValue('subject', next, { shouldDirty: true, shouldValidate: true }),
  )
  const insertIntoBody = (variable: string) => bodyEditorRef.current?.insertText(variable)

  const variablesQuery = useEmailTemplateVariables(moduleValue ?? 'work_orders')
  const variableCategories = variablesQuery.data ?? []

  const identityVisible = IDENTITY_FIELD_KEYS.some((key) => fieldPermission(key).visible)
  const contentVisible = fieldPermission('body').visible

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 p-4" noValidate>
          {identityVisible && (
            <FormSection
              icon={Mail}
              title={t('emailTemplates.form.sections.identity.title')}
              description={t('emailTemplates.form.sections.identity.description')}
            >
              <MetaField
                control={form.control}
                name="name"
                metaKey="name"
                label={t('emailTemplates.form.name')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="module"
                metaKey="module"
                label={t('emailTemplates.form.module')}
              >
                {({ field, disabled }) => (
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {EMAIL_TEMPLATE_MODULES.map((module) => (
                        <SelectItem key={module} value={module}>
                          {t(`emailTemplates.modules.${module}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="subject"
                metaKey="subject"
                label={t('emailTemplates.form.subject')}
              >
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
                        onSelect={trackSubjectSelection}
                        ref={(node) => {
                          field.ref(node)
                          subjectInputRef.current = node
                        }}
                      />
                    </FormControl>
                    <PlaceholderPicker
                      categories={variableCategories}
                      isLoading={variablesQuery.isLoading}
                      isError={variablesQuery.isError}
                      disabled={disabled}
                      onInsert={insertIntoSubject}
                    />
                  </div>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="description"
                metaKey="description"
                label={t('emailTemplates.form.description')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Textarea
                      disabled={disabled}
                      readOnly={readOnly}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="is_active"
                metaKey="is_active"
                label={t('emailTemplates.form.isActive')}
                layout="inline"
              >
                {({ field, disabled }) => (
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
                  </FormControl>
                )}
              </MetaField>
            </FormSection>
          )}

          {contentVisible && (
            <FormSection
              icon={FileText}
              title={t('emailTemplates.form.sections.content.title')}
              description={t('emailTemplates.form.sections.content.description')}
              aside={
                <PlaceholderPicker
                  categories={variableCategories}
                  isLoading={variablesQuery.isLoading}
                  isError={variablesQuery.isError}
                  onInsert={insertIntoBody}
                />
              }
            >
              <MetaField control={form.control} name="body" metaKey="body" label={t('emailTemplates.form.body')}>
                {({ field, disabled }) => (
                  <BodyFieldControl
                    value={field.value}
                    onChange={field.onChange}
                    disabled={disabled}
                    editorRef={bodyEditorRef}
                  />
                )}
              </MetaField>
            </FormSection>
          )}

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              className="bg-card"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('emailTemplates.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('emailTemplates.form.saving') : t('emailTemplates.form.save')}
            </Button>
          </div>
        </form>
      </Form>
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
 * `MetaField` render prop, same approach as `IconPickerControl` in
 * `task-importances`) and forwards the imperative ref the placeholder picker
 * inserts through. Images are disallowed (spec 0175 D-11).
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
