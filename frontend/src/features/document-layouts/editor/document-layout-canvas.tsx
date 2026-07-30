import { useMemo } from 'react'
import type { BlockSelection } from '@/features/document-layouts/editor/use-document-layout-editor'
import { DocumentLayoutZone } from '@/features/document-layouts/editor/document-layout-zone'
import type { ConfigValidationError } from '@/features/document-layouts/editor/config-validation-errors'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'
import { DOCUMENT_LAYOUT_ZONES } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutConfig, DocumentLayoutZoneName } from '@/features/document-layouts/layout-config'
import type { DefaultableBlockType } from '@/features/document-layouts/layout-config-defaults'

interface DocumentLayoutCanvasProps {
  config: DocumentLayoutConfig
  selection: BlockSelection | null
  configErrors: ConfigValidationError[]
  images: DocumentLayoutImage[]
  disabled: boolean
  onSelect: (selection: BlockSelection | null) => void
  onAdd: (zone: DocumentLayoutZoneName, type: DefaultableBlockType) => void
  onAddImage: (zone: DocumentLayoutZoneName, attachmentId: number) => void
  onRemove: (zone: DocumentLayoutZoneName, blockId: string) => void
  onReorder: (zone: DocumentLayoutZoneName, orderedIds: string[]) => void
}

/** Maps each zone's error block indices onto the block ids the rows actually key on. */
function errorIdsByZone(config: DocumentLayoutConfig, configErrors: ConfigValidationError[]): Map<DocumentLayoutZoneName, Set<string>> {
  const map = new Map<DocumentLayoutZoneName, Set<string>>(DOCUMENT_LAYOUT_ZONES.map((zone) => [zone, new Set<string>()]))
  for (const error of configErrors) {
    if (error.zone === null || error.blockIndex === null) {
      continue
    }
    const block = config[error.zone].blocks[error.blockIndex]
    if (block) {
      map.get(error.zone)?.add(block.id)
    }
  }
  return map
}

/** The three zones (header/body/footer), stacked — the editor's "canvas" pane (AC-120/AC-140). */
export function DocumentLayoutCanvas(props: DocumentLayoutCanvasProps) {
  const { config, selection, configErrors, images, disabled, onSelect, onAdd, onAddImage, onRemove, onReorder } = props
  const errorIds = useMemo(() => errorIdsByZone(config, configErrors), [config, configErrors])

  return (
    <div className="flex flex-col gap-3">
      {DOCUMENT_LAYOUT_ZONES.map((zone) => (
        <DocumentLayoutZone
          key={zone}
          zone={zone}
          blocks={config[zone].blocks}
          selectedBlockId={selection?.zone === zone ? selection.blockId : null}
          errorBlockIds={errorIds.get(zone) ?? EMPTY_ERROR_IDS}
          images={images}
          disabled={disabled}
          onSelect={onSelect}
          onAdd={onAdd}
          onAddImage={onAddImage}
          onRemove={onRemove}
          onReorder={onReorder}
        />
      ))}
    </div>
  )
}

const EMPTY_ERROR_IDS: ReadonlySet<string> = new Set()
