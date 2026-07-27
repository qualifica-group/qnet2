import type {
  LayoutBlob,
  LayoutColumns,
  LayoutItem,
  LayoutItemWidth,
  LayoutSection,
} from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Pure, immutable tree operations over a `LayoutBlob` (spec 0062
 * `layout-contract`) — the configurator's entire authoring model. Every
 * function takes the current blob and returns a NEW blob; none touch React
 * state directly, so `use-layout-configurator-actions.ts` (the only caller)
 * stays a thin wrapper and every operation here is unit-testable without
 * mounting a component or simulating a pointer drag (AC-009/AC-010).
 */

/** Fields a caller may patch on a section — everything but its identity/rows/order. */
export type SectionPatch = Partial<
  Pick<LayoutSection, 'title' | 'description' | 'variant' | 'collapsible' | 'default_collapsed' | 'columns'>
>

/** Where a dragged/removed attribute lands: back in the unplaced palette, or into a specific row. */
export type DropTarget =
  | { type: 'palette' }
  | { type: 'row'; sectionId: string; rowId: string; beforeCode: string | null }

const DEFAULT_SECTION_COLUMNS = 2

/** Client-stable id generator (spec `layout-contract`: "uuid client-generated, stabile"). */
export function createLayoutId(): string {
  return crypto.randomUUID()
}

function reindexSortOrder(sections: LayoutSection[]): LayoutSection[] {
  return sections.map((section, index) => ({ ...section, sort_order: index }))
}

function mapSection(
  blob: LayoutBlob,
  sectionId: string,
  transform: (section: LayoutSection) => LayoutSection,
): LayoutBlob {
  return {
    sections: blob.sections.map((section) => (section.id === sectionId ? transform(section) : section)),
  }
}

function swapAdjacent<T>(items: T[], index: number, direction: 'up' | 'down'): T[] {
  const target = direction === 'up' ? index - 1 : index + 1
  if (target < 0 || target >= items.length) {
    return items
  }
  const next = [...items]
  ;[next[index], next[target]] = [next[target], next[index]]
  return next
}

/** All `attribute_code`s currently placed anywhere in the blob. */
export function collectPlacedCodes(blob: LayoutBlob): Set<string> {
  const placed = new Set<string>()
  for (const section of blob.sections) {
    for (const row of section.rows) {
      for (const item of row.items) {
        placed.add(item.attribute_code)
      }
    }
  }
  return placed
}

/** The palette's contents: effective attributes not yet placed in any section, sort_order-stable. */
export function unplacedAttributes(
  blob: LayoutBlob,
  attributes: EffectiveAttribute[],
): EffectiveAttribute[] {
  const placed = collectPlacedCodes(blob)
  return attributes
    .filter((attribute) => !placed.has(attribute.code))
    .sort((a, b) => a.sort_order - b.sort_order)
}

interface AttributeLocation {
  sectionId: string
  rowId: string
  index: number
}

/** Finds which row (if any) currently holds `attributeCode`. */
export function locateAttribute(blob: LayoutBlob, attributeCode: string): AttributeLocation | null {
  for (const section of blob.sections) {
    for (const row of section.rows) {
      const index = row.items.findIndex((item) => item.attribute_code === attributeCode)
      if (index !== -1) {
        return { sectionId: section.id, rowId: row.id, index }
      }
    }
  }
  return null
}

/** Finds which section currently owns `rowId`. */
export function locateRow(blob: LayoutBlob, rowId: string): { sectionId: string } | null {
  for (const section of blob.sections) {
    if (section.rows.some((row) => row.id === rowId)) {
      return { sectionId: section.id }
    }
  }
  return null
}

/** Removes `attributeCode` from wherever it sits (no-op if already unplaced), returning its former item. */
function removeAttribute(blob: LayoutBlob, attributeCode: string): { blob: LayoutBlob; removed: LayoutItem | null } {
  let removed: LayoutItem | null = null
  const sections = blob.sections.map((section) => ({
    ...section,
    rows: section.rows.map((row) => {
      const found = row.items.find((item) => item.attribute_code === attributeCode)
      if (!found) {
        return row
      }
      removed = found
      return { ...row, items: row.items.filter((item) => item.attribute_code !== attributeCode) }
    }),
  }))
  return { blob: { sections }, removed }
}

function insertIntoRow(
  blob: LayoutBlob,
  sectionId: string,
  rowId: string,
  item: LayoutItem,
  beforeCode: string | null,
): LayoutBlob {
  return mapSection(blob, sectionId, (section) => ({
    ...section,
    rows: section.rows.map((row) => {
      if (row.id !== rowId) {
        return row
      }
      const insertAt = beforeCode ? row.items.findIndex((existing) => existing.attribute_code === beforeCode) : -1
      const items = insertAt === -1 ? [...row.items, item] : [...row.items.slice(0, insertAt), item, ...row.items.slice(insertAt)]
      return { ...row, items }
    }),
  }))
}

/**
 * The width a freshly placed attribute takes so it fills a SINGLE cell of the
 * target section instead of the whole row — otherwise setting a section to N
 * columns has no visible effect (the default would still span every column).
 * Each column count maps to the width that spans exactly one cell: 1->full,
 * 2->half, 3->third, 4->quarter. The user can still refine per item.
 */
function defaultWidthForColumns(columns: LayoutColumns): LayoutItemWidth {
  switch (columns) {
    case 1:
      return 'full'
    case 2:
      return 'half'
    case 3:
      return 'third'
    default:
      return 'quarter'
  }
}

/** Moves (or unplaces) one attribute — the single mutation backing every drag-and-drop outcome (AC-009). */
export function placeAttribute(blob: LayoutBlob, attributeCode: string, target: DropTarget): LayoutBlob {
  const { blob: withoutItem, removed } = removeAttribute(blob, attributeCode)
  if (target.type === 'palette') {
    return withoutItem
  }
  // A moved item keeps its width; a brand-new placement from the palette tiles
  // to the target section's column count (removed === undefined).
  const targetColumns = withoutItem.sections.find((section) => section.id === target.sectionId)?.columns ?? 1
  const width: LayoutItemWidth = removed?.width ?? defaultWidthForColumns(targetColumns)
  return insertIntoRow(withoutItem, target.sectionId, target.rowId, { attribute_code: attributeCode, width }, target.beforeCode)
}

/** Updates a placed item's width in place (AC-009: "cambio larghezza item aggiorna lo span"). */
export function updateItemWidth(blob: LayoutBlob, attributeCode: string, width: LayoutItemWidth): LayoutBlob {
  return {
    sections: blob.sections.map((section) => ({
      ...section,
      rows: section.rows.map((row) => ({
        ...row,
        items: row.items.map((item) => (item.attribute_code === attributeCode ? { ...item, width } : item)),
      })),
    })),
  }
}

/** A fresh, empty section appended at the end of the blob. */
export function createSection(blob: LayoutBlob): LayoutSection {
  return {
    id: createLayoutId(),
    title: '',
    description: null,
    variant: 'default',
    collapsible: false,
    default_collapsed: false,
    columns: DEFAULT_SECTION_COLUMNS,
    sort_order: blob.sections.length,
    rows: [],
  }
}

export function addSection(blob: LayoutBlob): LayoutBlob {
  return { sections: [...blob.sections, createSection(blob)] }
}

/** Removes a section; any items it held simply cease to exist in the blob (they return to the palette). */
export function removeSection(blob: LayoutBlob, sectionId: string): LayoutBlob {
  return { sections: reindexSortOrder(blob.sections.filter((section) => section.id !== sectionId)) }
}

export function updateSection(blob: LayoutBlob, sectionId: string, patch: SectionPatch): LayoutBlob {
  return mapSection(blob, sectionId, (section) => ({ ...section, ...patch }))
}

export function moveSection(blob: LayoutBlob, sectionId: string, direction: 'up' | 'down'): LayoutBlob {
  const index = blob.sections.findIndex((section) => section.id === sectionId)
  if (index === -1) {
    return blob
  }
  return { sections: reindexSortOrder(swapAdjacent(blob.sections, index, direction)) }
}

export function addRow(blob: LayoutBlob, sectionId: string): LayoutBlob {
  return mapSection(blob, sectionId, (section) => ({
    ...section,
    rows: [...section.rows, { id: createLayoutId(), items: [] }],
  }))
}

/** Removes a row; its items (if any) simply cease to exist in the blob (they return to the palette). */
export function removeRow(blob: LayoutBlob, sectionId: string, rowId: string): LayoutBlob {
  return mapSection(blob, sectionId, (section) => ({
    ...section,
    rows: section.rows.filter((row) => row.id !== rowId),
  }))
}

export function moveRow(blob: LayoutBlob, sectionId: string, rowId: string, direction: 'up' | 'down'): LayoutBlob {
  return mapSection(blob, sectionId, (section) => {
    const index = section.rows.findIndex((row) => row.id === rowId)
    if (index === -1) {
      return section
    }
    return { ...section, rows: swapAdjacent(section.rows, index, direction) }
  })
}
