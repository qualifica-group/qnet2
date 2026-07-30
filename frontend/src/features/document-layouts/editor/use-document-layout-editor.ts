import { useCallback, useState } from 'react'
import {
  addBlockToZone,
  addImageBlockToZone,
  removeBlockFromZone,
  reorderZoneBlocks,
  replaceBlockInZone,
  replacePage,
} from '@/features/document-layouts/editor/document-layout-editor-state'
import type { DefaultableBlockType } from '@/features/document-layouts/layout-config-defaults'
import type {
  Block,
  DocumentLayoutConfig,
  DocumentLayoutPage,
  DocumentLayoutZoneName,
} from '@/features/document-layouts/layout-config'

export interface BlockSelection {
  zone: DocumentLayoutZoneName
  blockId: string
}

interface UseDocumentLayoutEditorArgs {
  config: DocumentLayoutConfig
  onChange: (next: DocumentLayoutConfig) => void
}

/**
 * Thin, fully-controlled wrapper around `document-layout-editor-state.ts`'s
 * pure functions (same split as the attribute-layout configurator's
 * `use-layout-configurator-actions.ts`): reads `config` fresh on every
 * render, reports every mutation upward via `onChange` — the single source
 * of truth stays in the caller (`use-document-layout-form.ts`, which also
 * feeds the save payload and the AC-128 round-trip). `selection` is the only
 * local state here: which block is selected has no place in the saved
 * config.
 */
export function useDocumentLayoutEditor({ config, onChange }: UseDocumentLayoutEditorArgs) {
  const [selection, setSelection] = useState<BlockSelection | null>(null)

  const selectBlock = useCallback((next: BlockSelection | null) => setSelection(next), [])

  const addBlock = useCallback(
    (zone: DocumentLayoutZoneName, type: DefaultableBlockType) => {
      const next = addBlockToZone(config, zone, type)
      onChange(next)
      const added = next[zone].blocks[next[zone].blocks.length - 1]
      setSelection({ zone, blockId: added.id })
    },
    [config, onChange],
  )

  const addImageBlock = useCallback(
    (zone: DocumentLayoutZoneName, attachmentId: number) => {
      const next = addImageBlockToZone(config, zone, attachmentId)
      onChange(next)
      const added = next[zone].blocks[next[zone].blocks.length - 1]
      setSelection({ zone, blockId: added.id })
    },
    [config, onChange],
  )

  const removeBlock = useCallback(
    (zone: DocumentLayoutZoneName, blockId: string) => {
      onChange(removeBlockFromZone(config, zone, blockId))
      setSelection((current) => (current?.blockId === blockId ? null : current))
    },
    [config, onChange],
  )

  const reorderBlocks = useCallback(
    (zone: DocumentLayoutZoneName, orderedIds: string[]) => {
      onChange(reorderZoneBlocks(config, zone, orderedIds))
    },
    [config, onChange],
  )

  const updateBlock = useCallback(
    (zone: DocumentLayoutZoneName, next: Block) => {
      onChange(replaceBlockInZone(config, zone, next))
    },
    [config, onChange],
  )

  const setPage = useCallback(
    (next: DocumentLayoutPage) => {
      onChange(replacePage(config, next))
    },
    [config, onChange],
  )

  return { selection, selectBlock, addBlock, addImageBlock, removeBlock, reorderBlocks, updateBlock, setPage }
}
