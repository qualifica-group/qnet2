import { describe, expect, it } from 'vitest'
import {
  addRow,
  addSection,
  collectPlacedCodes,
  createLayoutId,
  moveRow,
  moveSection,
  placeAttribute,
  removeRow,
  removeSection,
  unplacedAttributes,
  updateItemWidth,
  updateSection,
} from '@/features/attributes/layout-configurator/layout-configurator-tree'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Pure-logic coverage for the configurator's tree operations (spec 0062
 * AC-009 drag/width, AC-010 section+row CRUD). Handler-level, not simulated
 * pointer drags — `attribute-layout-configurator.test.tsx` covers the wiring.
 */

function attribute(code: string, sortOrder = 0): EffectiveAttribute {
  return {
    id: sortOrder + 1,
    code,
    name: code,
    type: 'text',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: false,
    sort_order: sortOrder,
    inherited: false,
    context: 'product',
    options: [],
  }
}

const EMPTY_BLOB: LayoutBlob = { sections: [] }

describe('createLayoutId', () => {
  it('generates distinct stable ids', () => {
    expect(createLayoutId()).not.toBe(createLayoutId())
  })
})

describe('section CRUD (AC-010)', () => {
  it('adds a section with default columns/variant and sequential sort_order', () => {
    const withOne = addSection(EMPTY_BLOB)
    const withTwo = addSection(withOne)

    expect(withTwo.sections).toHaveLength(2)
    expect(withTwo.sections[0].sort_order).toBe(0)
    expect(withTwo.sections[1].sort_order).toBe(1)
    expect(withTwo.sections[1].variant).toBe('default')
    expect(withTwo.sections[1].columns).toBe(2)
  })

  it('renames a section and toggles its flags via updateSection', () => {
    const blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id

    const renamed = updateSection(blob, sectionId, {
      title: 'Identification',
      variant: 'highlighted',
      collapsible: true,
      default_collapsed: true,
      is_advanced: true,
      columns: 3,
    })

    expect(renamed.sections[0]).toMatchObject({
      title: 'Identification',
      variant: 'highlighted',
      collapsible: true,
      default_collapsed: true,
      is_advanced: true,
      columns: 3,
    })
  })

  it('reorders sections up/down and reindexes sort_order', () => {
    let blob = addSection(EMPTY_BLOB)
    blob = addSection(blob)
    const [first, second] = blob.sections

    const moved = moveSection(blob, second.id, 'up')

    expect(moved.sections.map((section) => section.id)).toEqual([second.id, first.id])
    expect(moved.sections[0].sort_order).toBe(0)
    expect(moved.sections[1].sort_order).toBe(1)
  })

  it('moving the first section up, or the last down, is a no-op', () => {
    const blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id

    expect(moveSection(blob, sectionId, 'up')).toEqual(blob)
    expect(moveSection(blob, sectionId, 'down')).toEqual(blob)
  })

  it('removing a section drops its rows entirely and reindexes remaining sections', () => {
    let blob = addSection(EMPTY_BLOB)
    blob = addSection(blob)
    const [first, second] = blob.sections
    blob = addRow(blob, first.id)
    blob = placeAttribute(blob, 'sku', { type: 'row', sectionId: first.id, rowId: blob.sections[0].rows[0].id, beforeCode: null })

    const withoutFirst = removeSection(blob, first.id)

    expect(withoutFirst.sections).toHaveLength(1)
    expect(withoutFirst.sections[0].id).toBe(second.id)
    expect(withoutFirst.sections[0].sort_order).toBe(0)
    // the removed section's item is gone from the blob -> back in the palette
    expect(collectPlacedCodes(withoutFirst).has('sku')).toBe(false)
  })
})

describe('row CRUD (AC-010)', () => {
  it('adds and removes empty rows within a section', () => {
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)

    expect(blob.sections[0].rows).toHaveLength(1)

    const rowId = blob.sections[0].rows[0].id
    blob = removeRow(blob, sectionId, rowId)

    expect(blob.sections[0].rows).toHaveLength(0)
  })

  it('removing a non-empty row returns its items to the palette', () => {
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id
    blob = placeAttribute(blob, 'sku', { type: 'row', sectionId, rowId, beforeCode: null })

    blob = removeRow(blob, sectionId, rowId)

    expect(collectPlacedCodes(blob).has('sku')).toBe(false)
  })

  it('reorders rows within a section (row-break order is preserved per row)', () => {
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    blob = addRow(blob, sectionId)
    const [firstRow, secondRow] = blob.sections[0].rows

    const moved = moveRow(blob, sectionId, secondRow.id, 'up')

    expect(moved.sections[0].rows.map((row) => row.id)).toEqual([secondRow.id, firstRow.id])
  })
})

describe('placeAttribute — drag placement/move/row-break (AC-009)', () => {
  it('places a palette attribute into a row, tiling to the section column count', () => {
    // A default section is 2 columns, so a fresh placement takes `half` (one
    // cell) — not `full` — otherwise the column setting would have no effect.
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id

    blob = placeAttribute(blob, 'company_name', { type: 'row', sectionId, rowId, beforeCode: null })

    expect(blob.sections[0].rows[0].items).toEqual([{ attribute_code: 'company_name', width: 'half' }])
  })

  it('a fresh palette placement takes the single-cell width for the section columns (1->full, 3->third)', () => {
    let one = addSection(EMPTY_BLOB)
    const oneId = one.sections[0].id
    one = updateSection(one, oneId, { columns: 1 })
    one = addRow(one, oneId)
    one = placeAttribute(one, 'a', { type: 'row', sectionId: oneId, rowId: one.sections[0].rows[0].id, beforeCode: null })
    expect(one.sections[0].rows[0].items[0].width).toBe('full')

    let three = addSection(EMPTY_BLOB)
    const threeId = three.sections[0].id
    three = updateSection(three, threeId, { columns: 3 })
    three = addRow(three, threeId)
    three = placeAttribute(three, 'b', { type: 'row', sectionId: threeId, rowId: three.sections[0].rows[0].id, beforeCode: null })
    expect(three.sections[0].rows[0].items[0].width).toBe('third')
  })

  it('moves a placed item to a different row, preserving its width', () => {
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    blob = addRow(blob, sectionId)
    const [rowA, rowB] = blob.sections[0].rows
    blob = placeAttribute(blob, 'sku', { type: 'row', sectionId, rowId: rowA.id, beforeCode: null })
    blob = updateItemWidth(blob, 'sku', 'half')

    blob = placeAttribute(blob, 'sku', { type: 'row', sectionId, rowId: rowB.id, beforeCode: null })

    expect(blob.sections[0].rows[0].items).toEqual([])
    expect(blob.sections[0].rows[1].items).toEqual([{ attribute_code: 'sku', width: 'half' }])
  })

  it('moves an item between two SECTIONS, respecting the row break of each', () => {
    let blob = addSection(EMPTY_BLOB)
    blob = addSection(blob)
    const [sectionA, sectionB] = blob.sections
    blob = addRow(blob, sectionA.id)
    blob = addRow(blob, sectionB.id)
    const rowA = blob.sections[0].rows[0]
    const rowB = blob.sections[1].rows[0]
    blob = placeAttribute(blob, 'weight', { type: 'row', sectionId: sectionA.id, rowId: rowA.id, beforeCode: null })

    blob = placeAttribute(blob, 'weight', { type: 'row', sectionId: sectionB.id, rowId: rowB.id, beforeCode: null })

    expect(blob.sections[0].rows[0].items).toEqual([])
    // Placed into a default 2-column section (`half`), then moved — the move preserves that width.
    expect(blob.sections[1].rows[0].items).toEqual([{ attribute_code: 'weight', width: 'half' }])
  })

  it('inserting before an existing item keeps a manual row break intact (two rows stay distinct)', () => {
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    blob = addRow(blob, sectionId)
    const [rowA, rowB] = blob.sections[0].rows
    blob = placeAttribute(blob, 'a', { type: 'row', sectionId, rowId: rowA.id, beforeCode: null })
    blob = placeAttribute(blob, 'b', { type: 'row', sectionId, rowId: rowB.id, beforeCode: null })

    blob = placeAttribute(blob, 'c', { type: 'row', sectionId, rowId: rowB.id, beforeCode: 'b' })

    expect(blob.sections[0].rows[0].items.map((item) => item.attribute_code)).toEqual(['a'])
    expect(blob.sections[0].rows[1].items.map((item) => item.attribute_code)).toEqual(['c', 'b'])
  })

  it('unplaces an item back to the palette', () => {
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id
    blob = placeAttribute(blob, 'sku', { type: 'row', sectionId, rowId, beforeCode: null })

    blob = placeAttribute(blob, 'sku', { type: 'palette' })

    expect(blob.sections[0].rows[0].items).toEqual([])
    expect(collectPlacedCodes(blob).has('sku')).toBe(false)
  })
})

describe('updateItemWidth (AC-009: "cambio larghezza item aggiorna lo span")', () => {
  it('updates only the targeted item width', () => {
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id
    blob = placeAttribute(blob, 'a', { type: 'row', sectionId, rowId, beforeCode: null })
    blob = placeAttribute(blob, 'b', { type: 'row', sectionId, rowId, beforeCode: null })

    // Both start at the 2-column default (`half`); updating one to `third` must not touch the other.
    blob = updateItemWidth(blob, 'a', 'third')

    expect(blob.sections[0].rows[0].items).toEqual([
      { attribute_code: 'a', width: 'third' },
      { attribute_code: 'b', width: 'half' },
    ])
  })
})

describe('unplacedAttributes', () => {
  it('excludes placed codes and sorts by sort_order', () => {
    let blob = addSection(EMPTY_BLOB)
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id
    blob = placeAttribute(blob, 'b', { type: 'row', sectionId, rowId, beforeCode: null })

    const attributes = [attribute('c', 2), attribute('a', 0), attribute('b', 1)]

    expect(unplacedAttributes(blob, attributes).map((item) => item.code)).toEqual(['a', 'c'])
  })
})
