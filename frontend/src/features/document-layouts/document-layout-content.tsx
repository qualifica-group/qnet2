import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { MetaField } from '@/features/authorization/MetaField'
import { DocumentLayoutEditor } from '@/features/document-layouts/editor/document-layout-editor'
import type { ConfigValidationError } from '@/features/document-layouts/editor/config-validation-errors'
import { useDocumentLayoutImages } from '@/features/document-layouts/editor/images/document-layout-images-api'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'
import { DocumentLayoutPreview } from '@/features/document-layouts/editor/preview/document-layout-preview'
import { useDocumentLayoutVariables } from '@/features/document-layouts/variables-api'
import type { DocumentLayoutFormValues } from '@/features/document-layouts/use-document-layout-form'

const EMPTY_IMAGES: DocumentLayoutImage[] = []

interface DocumentLayoutContentEditorProps {
  /** `null` for an unsaved (create) layout: gates image upload (AC-126). */
  layoutId: number | null
  configErrors: ConfigValidationError[]
}

/**
 * The visual block editor bound to the form's `config`, under its field
 * permission (`MetaField`: disabled when not editable). Shared by the
 * detail's row and the create form, where it stays open.
 */
export function DocumentLayoutContentEditor({ layoutId, configErrors }: DocumentLayoutContentEditorProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<DocumentLayoutFormValues>()
  const module = useWatch({ control, name: 'module' })

  return (
    <MetaField control={control} name="config" metaKey="config" label={t('documentLayouts.form.tabs.content')}>
      {({ field, disabled }) => (
        <DocumentLayoutEditor
          config={field.value}
          onChange={field.onChange}
          module={module}
          layoutId={layoutId}
          configErrors={configErrors}
          disabled={disabled}
        />
      )}
    </MetaField>
  )
}

interface DocumentLayoutContentRowProps extends DocumentLayoutContentEditorProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The detail's content: one full-width row, an A4 preview until its pencil opens the same editor as creation. */
export function DocumentLayoutContentRow({ inline, canEdit, layoutId, configErrors }: DocumentLayoutContentRowProps) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="config"
      label={t('documentLayouts.form.tabs.content')}
      layout="block"
      inline={inline}
      canEdit={canEdit}
      editor={<DocumentLayoutContentEditor layoutId={layoutId} configErrors={configErrors} />}
    >
      <ClosedPreview layoutId={layoutId} />
    </RecordInlineField>
  )
}

function ClosedPreview({ layoutId }: { layoutId: number | null }) {
  const { control } = useFormContext<DocumentLayoutFormValues>()
  const config = useWatch({ control, name: 'config' })
  const module = useWatch({ control, name: 'module' })
  const variables = useDocumentLayoutVariables(module)
  const images = useDocumentLayoutImages(layoutId)

  return (
    <div className="overflow-auto rounded-md border border-border bg-muted/20 p-3">
      <DocumentLayoutPreview config={config} images={images.data ?? EMPTY_IMAGES} variablesCatalog={variables.data} />
    </div>
  )
}
