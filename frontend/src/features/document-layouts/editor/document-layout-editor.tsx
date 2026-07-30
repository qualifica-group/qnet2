import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ConfigSection } from '@/components/ui/config-section'
import type { ConfigValidationError } from '@/features/document-layouts/editor/config-validation-errors'
import { DocumentLayoutCanvas } from '@/features/document-layouts/editor/document-layout-canvas'
import { BlockInspector } from '@/features/document-layouts/editor/inspector/block-inspector'
import { useDocumentLayoutImages } from '@/features/document-layouts/editor/images/document-layout-images-api'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'
import { PageSettingsPanel } from '@/features/document-layouts/editor/page/page-settings-panel'
import { DocumentLayoutPreview } from '@/features/document-layouts/editor/preview/document-layout-preview'
import { useDocumentLayoutEditor } from '@/features/document-layouts/editor/use-document-layout-editor'
import type { BlockSelection } from '@/features/document-layouts/editor/use-document-layout-editor'
import { VariablePicker } from '@/features/document-layouts/editor/variables/variable-picker'
import type { DocumentLayoutConfig } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutModule } from '@/features/document-layouts/types'
import { useDocumentLayoutVariables } from '@/features/document-layouts/variables-api'

const EMPTY_IMAGES: DocumentLayoutImage[] = []

export interface DocumentLayoutEditorProps {
  config: DocumentLayoutConfig
  onChange: (next: DocumentLayoutConfig) => void
  module: DocumentLayoutModule
  /** `null` for an unsaved (create-mode) layout — gates image upload (AC-126). */
  layoutId: number | null
  configErrors: ConfigValidationError[]
  disabled?: boolean
}

/**
 * The visual block editor (spec 0069 MT-7/MT-8): three panes — canvas
 * (zones + blocks, AC-120), inspector (page settings always reachable +
 * selected block's type-specific editor, AC-121/AC-123/AC-124/AC-126) and
 * the searchable variable picker (AC-122) — plus a live A4 preview
 * (AC-127). `config`/`onChange` are fully controlled by the caller
 * (`use-document-layout-form.ts`), which is also what makes the save
 * round-trip exact (AC-128): this component never holds its own copy of the
 * config. Stacks to one column below `md` (AC-140).
 */
export function DocumentLayoutEditor({ config, onChange, module, layoutId, configErrors, disabled = false }: DocumentLayoutEditorProps) {
  const { t } = useTranslation()
  const editor = useDocumentLayoutEditor({ config, onChange })
  const variablesQuery = useDocumentLayoutVariables(module)
  const imagesQuery = useDocumentLayoutImages(layoutId)
  const [activeInsert, setActiveInsert] = useState<((token: string) => void) | null>(null)
  const images = imagesQuery.data ?? EMPTY_IMAGES

  function handleSelect(selection: BlockSelection | null) {
    setActiveInsert(null)
    editor.selectBlock(selection)
  }

  // `useState`'s setter treats a bare function argument as a functional
  // updater, not the next value — wrap so the run's own `insertAtCaret`
  // becomes the stored state instead of being invoked with the previous one.
  function activateRunInsert(insert: (token: string) => void) {
    setActiveInsert(() => insert)
  }

  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,0.9fr)]">
      <div className="flex min-w-0 flex-col gap-4">
        <DocumentLayoutCanvas
          config={config}
          selection={editor.selection}
          configErrors={configErrors}
          images={images}
          disabled={disabled}
          onSelect={handleSelect}
          onAdd={editor.addBlock}
          onAddImage={editor.addImageBlock}
          onRemove={editor.removeBlock}
          onReorder={editor.reorderBlocks}
        />
        <div className="overflow-auto rounded-md border border-border bg-muted/20 p-3">
          <DocumentLayoutPreview config={config} images={images} variablesCatalog={variablesQuery.data} />
        </div>
      </div>

      <div className="flex min-w-0 flex-col gap-3">
        <ConfigSection
          title={t('documentLayouts.editor.page.title')}
          variant="secondary"
          collapsible
          defaultCollapsed
        >
          <PageSettingsPanel page={config.page} onChange={editor.setPage} disabled={disabled} />
        </ConfigSection>
        <ConfigSection title={t('documentLayouts.editor.inspector.title')} variant="secondary">
          <BlockInspector
            selection={editor.selection}
            config={config}
            layoutId={layoutId}
            variablesCatalog={variablesQuery.data}
            configErrors={configErrors}
            disabled={disabled}
            onUpdateBlock={editor.updateBlock}
            onActivateRunInsert={activateRunInsert}
          />
        </ConfigSection>
      </div>

      <div className="min-w-0">
        <VariablePicker
          catalog={variablesQuery.data}
          isLoading={variablesQuery.isLoading}
          disabled={!activeInsert}
          onInsert={(variable) => activeInsert?.(variable)}
        />
      </div>
    </div>
  )
}
