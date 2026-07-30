import { createDefaultBlock, createDefaultImageBlock } from '@/features/document-layouts/layout-config-defaults'
import type { DefaultableBlockType } from '@/features/document-layouts/layout-config-defaults'
import type {
  Block,
  DocumentLayoutConfig,
  DocumentLayoutPage,
  DocumentLayoutZoneName,
} from '@/features/document-layouts/layout-config'

/**
 * Pure, immutable operations over a `DocumentLayoutConfig` (spec 0069
 * `config_schema`) — the visual editor's entire authoring model. Every
 * function takes the current config and returns a NEW config; none touch
 * React state directly, so `use-document-layout-editor.ts` (the only caller)
 * stays a thin wrapper and every operation is unit-testable without mounting
 * a component or simulating a drag gesture (AC-120). Same split as the
 * attribute-layout configurator's `layout-configurator-tree.ts`.
 */

/** Client-stable block id generator (mirrors the attribute-layout configurator's `createLayoutId`). */
export function createBlockId(): string {
  return crypto.randomUUID()
}

function mapZone(
  config: DocumentLayoutConfig,
  zone: DocumentLayoutZoneName,
  transform: (blocks: Block[]) => Block[],
): DocumentLayoutConfig {
  return { ...config, [zone]: { blocks: transform(config[zone].blocks) } }
}

/** Appends a new block of `type` to `zone`, seeded with its type default (AC-120). */
export function addBlockToZone(
  config: DocumentLayoutConfig,
  zone: DocumentLayoutZoneName,
  type: DefaultableBlockType,
): DocumentLayoutConfig {
  return mapZone(config, zone, (blocks) => [...blocks, createDefaultBlock(type, createBlockId())])
}

/** Appends a new `image` block referencing `attachmentId` (no uniform default factory, spec 0069). */
export function addImageBlockToZone(
  config: DocumentLayoutConfig,
  zone: DocumentLayoutZoneName,
  attachmentId: number,
): DocumentLayoutConfig {
  return mapZone(config, zone, (blocks) => [...blocks, createDefaultImageBlock(createBlockId(), attachmentId)])
}

/** Removes a block from `zone` only (AC-120: "rimuoverlo lo elimina dalla sola zona interessata"). */
export function removeBlockFromZone(
  config: DocumentLayoutConfig,
  zone: DocumentLayoutZoneName,
  blockId: string,
): DocumentLayoutConfig {
  return mapZone(config, zone, (blocks) => blocks.filter((block) => block.id !== blockId))
}

/** Reorders `zone`'s blocks to match `orderedIds`; every other zone is untouched (AC-120). */
export function reorderZoneBlocks(
  config: DocumentLayoutConfig,
  zone: DocumentLayoutZoneName,
  orderedIds: string[],
): DocumentLayoutConfig {
  return mapZone(config, zone, (blocks) => {
    const byId = new Map(blocks.map((block) => [block.id, block]))
    return orderedIds
      .map((id) => byId.get(id))
      .filter((block): block is Block => block !== undefined)
  })
}

/** Replaces one block in `zone` with `next` (matched by `id`) — used by every per-type inspector. */
export function replaceBlockInZone(
  config: DocumentLayoutConfig,
  zone: DocumentLayoutZoneName,
  next: Block,
): DocumentLayoutConfig {
  return mapZone(config, zone, (blocks) => blocks.map((block) => (block.id === next.id ? next : block)))
}

/** Replaces the `page` settings wholesale — the page panel owns merging margins/font (AC-124). */
export function replacePage(config: DocumentLayoutConfig, next: DocumentLayoutPage): DocumentLayoutConfig {
  return { ...config, page: next }
}
