import { useTranslation } from 'react-i18next'
import { BlockInspectorDispatch } from '@/features/document-layouts/editor/inspector/block-inspector-dispatch'
import type { ConfigValidationError } from '@/features/document-layouts/editor/config-validation-errors'
import type { BlockSelection } from '@/features/document-layouts/editor/use-document-layout-editor'
import type { Block, DocumentLayoutConfig, DocumentLayoutZoneName } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

interface BlockInspectorProps {
  selection: BlockSelection | null
  config: DocumentLayoutConfig
  layoutId: number | null
  variablesCatalog?: DocumentLayoutVariablesCatalog
  configErrors: ConfigValidationError[]
  disabled: boolean
  onUpdateBlock: (zone: DocumentLayoutZoneName, next: Block) => void
  onActivateRunInsert: (insert: (token: string) => void) => void
}

/**
 * Resolves the selected block from `config` and hands it to
 * `BlockInspectorDispatch`, surfacing any 422 config errors that landed on
 * this exact block (AC-129) above its type-specific fields. Keyed by
 * `block.id` so switching the selection fully remounts the inspector,
 * resetting any local per-type editor state (e.g. the products-table
 * column drag-id map) cleanly instead of carrying it over.
 */
export function BlockInspector({
  selection,
  config,
  layoutId,
  variablesCatalog,
  configErrors,
  disabled,
  onUpdateBlock,
  onActivateRunInsert,
}: BlockInspectorProps) {
  const { t } = useTranslation()

  if (!selection) {
    return <p className="text-xs text-muted-foreground italic">{t('documentLayouts.editor.inspector.empty')}</p>
  }

  const zoneBlocks = config[selection.zone].blocks
  const block = zoneBlocks.find((candidate) => candidate.id === selection.blockId)
  if (!block) {
    return <p className="text-xs text-muted-foreground italic">{t('documentLayouts.editor.inspector.empty')}</p>
  }

  const blockIndex = zoneBlocks.indexOf(block)
  const errors = configErrors.filter((error) => error.zone === selection.zone && error.blockIndex === blockIndex)

  return (
    <div key={block.id} className="flex flex-col gap-3">
      {errors.length > 0 && (
        <ul
          role="alert"
          className="flex flex-col gap-1 rounded-md border border-destructive/40 bg-destructive/5 p-2 text-xs text-destructive"
        >
          {errors.map((error, index) => (
            <li key={index}>{error.message}</li>
          ))}
        </ul>
      )}
      <BlockInspectorDispatch
        zone={selection.zone}
        block={block}
        layoutId={layoutId}
        variablesCatalog={variablesCatalog}
        disabled={disabled}
        onChange={(next) => onUpdateBlock(selection.zone, next)}
        onActivateRunInsert={onActivateRunInsert}
      />
    </div>
  )
}
