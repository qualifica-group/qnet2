import { useMemo } from 'react'
import { DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors } from '@dnd-kit/core'
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import '@/features/attributes/layout-configurator/i18n'
import { AttributeLayoutPalette } from '@/features/attributes/layout-configurator/attribute-layout-palette'
import { AttributeLayoutPreviewPanel } from '@/features/attributes/layout-configurator/attribute-layout-preview-panel'
import { AttributeLayoutSectionEditor } from '@/features/attributes/layout-configurator/attribute-layout-section-editor'
import { unplacedAttributes } from '@/features/attributes/layout-configurator/layout-configurator-tree'
import { useLayoutConfiguratorActions } from '@/features/attributes/layout-configurator/use-layout-configurator-actions'
import type { LayoutBlob, LayoutFormMode } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

export interface AttributeLayoutConfiguratorProps {
  /** The layout being authored — fully controlled by the caller (spec 0062 MT-3.2 owns fetch/save). */
  blob: LayoutBlob
  onChange: (next: LayoutBlob) => void
  /** The category's effective attributes for this context — palette source + preview data. */
  attributes: EffectiveAttribute[]
  /** Which form mode this layout targets; only affects the live preview's control read-only-ness. */
  mode: LayoutFormMode
  disabled?: boolean
}

/**
 * The drag-and-drop layout configurator (spec 0062 MT-3.1): a palette of
 * unplaced attributes, the sections/rows/items editor, and a live preview —
 * three panes sharing ONE `DndContext` scoped to attribute placement
 * (AC-009). Section/row order changes are plain buttons (§ `attribute-layout-
 * row-editor.tsx`), not drag, to keep drag ids unambiguous (every draggable
 * id is an `attribute_code`, globally unique across the blob).
 */
export function AttributeLayoutConfigurator({
  blob,
  onChange,
  attributes,
  mode,
  disabled = false,
}: AttributeLayoutConfiguratorProps) {
  const { t } = useTranslation('attributeLayout')
  const actions = useLayoutConfiguratorActions({ blob, onChange })
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )
  const attributesByCode = useMemo(() => new Map(attributes.map((attribute) => [attribute.code, attribute])), [attributes])
  const palette = unplacedAttributes(blob, attributes)

  return (
    <div className="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:items-start">
      <div className="flex min-w-0 flex-col gap-3">
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={actions.handleDragEnd}>
          <AttributeLayoutPalette attributes={palette} disabled={disabled} />
          <div className="flex flex-col gap-3">
            {blob.sections.map((section, index) => (
              <AttributeLayoutSectionEditor
                key={section.id}
                section={section}
                attributesByCode={attributesByCode}
                isFirst={index === 0}
                isLast={index === blob.sections.length - 1}
                disabled={disabled}
                actions={actions}
              />
            ))}
          </div>
        </DndContext>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="border-dashed text-muted-foreground hover:text-foreground"
          disabled={disabled}
          onClick={actions.addSection}
        >
          <Plus aria-hidden="true" />
          {t('configurator.addSection')}
        </Button>
      </div>

      <AttributeLayoutPreviewPanel blob={blob} attributes={attributes} mode={mode} />
    </div>
  )
}
