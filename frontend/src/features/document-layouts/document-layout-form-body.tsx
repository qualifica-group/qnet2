import { useState } from 'react'
import { FileText } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { DocumentLayoutEditor } from '@/features/document-layouts/editor/document-layout-editor'
import { useDocumentLayoutForm } from '@/features/document-layouts/use-document-layout-form'
import { DOCUMENT_LAYOUT_MODULES } from '@/features/document-layouts/types'
import type {
  DocumentLayoutDetail,
  DocumentLayoutFormMode,
} from '@/features/document-layouts/types'

/** Tab keys of the form's own `Tabs` (metadata vs. the visual block editor, spec 0069 wave 2). */
const DETAILS_TAB = 'details'
const CONTENT_TAB = 'content'

interface DocumentLayoutFormBodyProps {
  mode: DocumentLayoutFormMode
  onSuccess: (documentLayout: DocumentLayoutDetail) => void
  onCancel: () => void
}

/**
 * The document layout create/edit form UI: a `Tabs` strip splitting the
 * metadata fields (spec 0069 wave 1 — `name`, `code`, `description`,
 * `module`, `is_active`, `is_default`, each wrapped in `MetaField`, spec
 * 0004) from the visual block editor (spec 0069 wave 2, MT-7/MT-8) that owns
 * `config`. `code`/`module` immutability after create is NOT a frontend
 * decision: the backend's field-permission ceiling reports both
 * `editable: true` only when there is no model context (create), so on edit
 * `MetaField` disables them automatically — the same mechanism every other
 * field uses. `config` follows the identical pattern via `fieldPermission
 * ('config')`: the "Content" tab is hidden when the field itself is not
 * visible, and the editor renders disabled (no add/remove/edit) when it is
 * visible but not editable. All non-render logic lives in
 * `useDocumentLayoutForm`.
 */
export function DocumentLayoutFormBody({ mode, onSuccess, onCancel }: DocumentLayoutFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit, config, onConfigChange, configErrors } = useDocumentLayoutForm({
    mode,
    onSuccess,
  })

  const identityVisible =
    fieldPermission('name').visible ||
    fieldPermission('code').visible ||
    fieldPermission('description').visible ||
    fieldPermission('module').visible ||
    fieldPermission('is_active').visible ||
    fieldPermission('is_default').visible
  const configVisible = fieldPermission('config').visible
  const moduleValue = form.watch('module')
  const [activeTab, setActiveTab] = useState(DETAILS_TAB)
  const [lastSeenConfigErrors, setLastSeenConfigErrors] = useState(configErrors)

  // A config-path 422 (AC-129) lands on a field the "Details" tab never
  // shows: jump to "Content" so the block-level message (rendered by
  // `BlockInspector`) is actually reachable instead of only mentioned in the
  // generic banner below. Adjusted during render (React's "adjusting state
  // on prop change" recipe) rather than an effect: `configErrors` is a new
  // array identity only on an actual submit failure, so this fires once per
  // failed submit, not on every render.
  if (configErrors !== lastSeenConfigErrors) {
    setLastSeenConfigErrors(configErrors)
    if (configErrors.length > 0) {
      setActiveTab(CONTENT_TAB)
    }
  }

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-1 flex-col gap-4 p-4"
          noValidate
        >
          <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-1 flex-col gap-3">
            <TabsList className="w-fit">
              <TabsTrigger value={DETAILS_TAB}>{t('documentLayouts.form.tabs.details')}</TabsTrigger>
              {configVisible && <TabsTrigger value={CONTENT_TAB}>{t('documentLayouts.form.tabs.content')}</TabsTrigger>}
            </TabsList>

            <TabsContent value={DETAILS_TAB} className="flex flex-col gap-4">
              {identityVisible && (
                <FormSection
                  icon={FileText}
                  title={t('documentLayouts.form.sections.identity.title')}
                  description={t('documentLayouts.form.sections.identity.description')}
                >
                  <MetaField
                    control={form.control}
                    name="name"
                    metaKey="name"
                    label={t('documentLayouts.form.name')}
                  >
                    {({ field, disabled, readOnly }) => (
                      <FormControl>
                        <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
                      </FormControl>
                    )}
                  </MetaField>

                  <MetaField
                    control={form.control}
                    name="code"
                    metaKey="code"
                    label={t('documentLayouts.form.code')}
                    hint={mode.type === 'edit' ? t('documentLayouts.form.hints.codeLocked') : undefined}
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
                    label={t('documentLayouts.form.module')}
                    hint={mode.type === 'edit' ? t('documentLayouts.form.hints.moduleLocked') : undefined}
                  >
                    {({ field, disabled }) => (
                      <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {DOCUMENT_LAYOUT_MODULES.map((module) => (
                            <SelectItem key={module} value={module}>
                              {t(`documentLayouts.modules.${module}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    )}
                  </MetaField>

                  <MetaField
                    control={form.control}
                    name="description"
                    metaKey="description"
                    label={t('documentLayouts.form.description')}
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
                    label={t('documentLayouts.form.isActive')}
                  >
                    {({ field, disabled }) => (
                      <FormControl>
                        <Switch
                          checked={field.value}
                          onCheckedChange={field.onChange}
                          disabled={disabled}
                        />
                      </FormControl>
                    )}
                  </MetaField>

                  <MetaField
                    control={form.control}
                    name="is_default"
                    metaKey="is_default"
                    label={t('documentLayouts.form.isDefault')}
                    description={t('documentLayouts.form.hints.isDefault')}
                  >
                    {({ field, disabled }) => (
                      <FormControl>
                        <Switch
                          checked={field.value}
                          onCheckedChange={field.onChange}
                          disabled={disabled}
                        />
                      </FormControl>
                    )}
                  </MetaField>
                </FormSection>
              )}
            </TabsContent>

            {configVisible && (
              <TabsContent value={CONTENT_TAB} className="min-h-0 flex-1">
                <DocumentLayoutEditor
                  config={config}
                  onChange={onConfigChange}
                  module={moduleValue}
                  layoutId={mode.type === 'edit' ? mode.documentLayout.id : null}
                  configErrors={configErrors}
                  disabled={!fieldPermission('config').editable}
                />
              </TabsContent>
            )}
          </Tabs>

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('documentLayouts.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('documentLayouts.form.saving')
                : t('documentLayouts.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
