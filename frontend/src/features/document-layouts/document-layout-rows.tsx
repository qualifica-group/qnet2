import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { RecordField } from '@/components/detail/record-panel'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { configAfterModuleChange } from '@/features/document-layouts/invoice-layout-starter'
import { DOCUMENT_LAYOUT_MODULES } from '@/features/document-layouts/types'
import type { DocumentLayoutModule } from '@/features/document-layouts/types'
import type { DocumentLayoutFormValues } from '@/features/document-layouts/use-document-layout-form'

interface DocumentLayoutRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** `code` and `module` are immutable after create: editable rows on the create draft, plain on the detail. */
  creating: boolean
}

/** The layout's header rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function DocumentLayoutRows({ inline, canEdit, creating }: DocumentLayoutRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<DocumentLayoutFormValues>
        name="name"
        label={t('documentLayouts.form.name')}
        inline={inline}
        canEdit={canEdit}
      />
      {creating ? (
        <>
          <TextInlineRow<DocumentLayoutFormValues> name="code" label={t('documentLayouts.form.code')} inline={inline} />
          <ModuleRow inline={inline} />
        </>
      ) : (
        <ModuleLockedRow />
      )}
      <TextInlineRow<DocumentLayoutFormValues>
        name="description"
        label={t('documentLayouts.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <SwitchInlineRow<DocumentLayoutFormValues>
        name="is_active"
        label={t('documentLayouts.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<DocumentLayoutFormValues>
        name="is_default"
        label={t('documentLayouts.form.isDefault')}
        hint={t('documentLayouts.form.hints.isDefault')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}

function useModuleLabel() {
  const { t } = useTranslation()
  const { control } = useFormContext<DocumentLayoutFormValues>()
  const module = useWatch({ control, name: 'module' })
  return t(`documentLayouts.modules.${module}`)
}

function ModuleLockedRow() {
  const { t } = useTranslation()
  return <RecordField label={t('documentLayouts.detail.module')}>{useModuleLabel()}</RecordField>
}

/** Create-only module picker: while the content is untouched, a new module swaps in its starter layout. */
function ModuleRow({ inline }: { inline: InlineEdit }) {
  const { t } = useTranslation()
  const { control, getValues, setValue } = useFormContext<DocumentLayoutFormValues>()
  const label = t('documentLayouts.form.module')
  const moduleLabel = useModuleLabel()

  const changeModule = (module: DocumentLayoutModule) => {
    setValue('module', module, { shouldDirty: true })
    setValue('config', configAfterModuleChange(getValues('config'), module), { shouldDirty: true })
  }

  return (
    <RecordInlineField
      field="module"
      label={label}
      inline={inline}
      editor={
        <MetaField control={control} name="module" metaKey="module" label={label}>
          {({ field, disabled }) => (
            <Select value={field.value} onValueChange={(value) => changeModule(value as DocumentLayoutModule)} disabled={disabled}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {DOCUMENT_LAYOUT_MODULES.map((value) => (
                  <SelectItem key={value} value={value}>
                    {t(`documentLayouts.modules.${value}`)}
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
  )
}
